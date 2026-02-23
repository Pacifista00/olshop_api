<?php

namespace App\Http\Controllers;

use App\Models\MidtransTransaction;
use App\Models\Order;
use App\Models\PointHistory;
use App\Models\Product;
use App\Models\UserPoint;
use App\Models\Voucher;
use App\Services\MidtransService;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransController extends Controller
{
    public function handle(Request $request)
    {
        MidtransService::init();

        $payload = $request->all();

        if (!MidtransService::verifySignature($payload)) {
            Log::warning('Midtrans invalid signature', $payload);
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        try {

            DB::transaction(function () use ($payload) {

                $order = Order::where('order_number', $payload['order_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                /**
                 * =========================================
                 * 🚫 GUARD: JANGAN PROSES ORDER CANCELLED
                 * =========================================
                 */
                if ($order->status === Order::STATUS_CANCELLED) {
                    Log::warning('Webhook ignored: order already cancelled', [
                        'order_id' => $order->id
                    ]);
                    return;
                }

                $order->load(['items', 'user']);

                /**
                 * =========================================
                 * BANK-GRADE GROSS CHECK
                 * =========================================
                 */
                if (bccomp((string) $payload['gross_amount'], (string) $order->total_amount, 2) !== 0) {

                    Log::critical('Gross amount mismatch', [
                        'order_id' => $order->id,
                        'midtrans' => $payload['gross_amount'],
                        'local' => $order->total_amount,
                    ]);

                    $order->update([
                        'status' => Order::STATUS_CANCELLED,
                    ]);

                    return;
                }

                MidtransTransaction::updateOrCreate(
                    ['midtrans_transaction_id' => $payload['transaction_id']],
                    [
                        'order_id' => $order->id,
                        'status_code' => $payload['status_code'],
                        'transaction_status' => $payload['transaction_status'],
                        'payment_type' => $payload['payment_type'] ?? null,
                        'va_number' => $payload['va_numbers'][0]['va_number'] ?? null,
                        'json_data' => $payload,
                    ]
                );

                $status = MidtransService::mapPaymentStatus(
                    $payload['transaction_status'],
                    $payload['fraud_status'] ?? null
                );

                $wasPaid = $order->payment_status === Order::PAYMENT_PAID;

                $order->update([
                    'payment_status' => $status['payment_status'],
                    'status' => $status['status'],
                    'payment_method' => $payload['payment_type'] ?? null,
                    'paid_at' => $status['payment_status'] === 'paid'
                        ? ($order->paid_at ?? now())
                        : null,
                    'midtrans_response' => $payload,
                ]);

                /**
                 * =========================================
                 * 🔓 RELEASE RESERVED STOCK (IMPORTANT)
                 * =========================================
                 */
                if (
                    !$wasPaid &&
                    in_array($status['payment_status'], ['failed', 'expired', 'cancelled'])
                ) {
                    foreach ($order->items as $item) {

                        $product = Product::lockForUpdate()->find($item->product_id);

                        if (!$product) {
                            continue;
                        }

                        $newReserved = max(0, $product->reserved_stock - $item->quantity);

                        $product->update([
                            'reserved_stock' => $newReserved
                        ]);
                    }
                }

                /**
                 * =========================================
                 * BUSINESS EFFECT — ONCE ONLY
                 * =========================================
                 */
                if (!$wasPaid && $status['payment_status'] === 'paid') {

                    /**
                     * 🔒 deduct points
                     */
                    if ($order->points_used > 0 && !$order->points_deducted) {

                        $userPoint = UserPoint::lockForUpdate()
                            ->where('user_id', $order->user_id)
                            ->first();

                        if ($userPoint) {
                            $userPoint->decrement('total_points', $order->points_used);

                            PointHistory::create([
                                'user_id' => $order->user_id,
                                'order_id' => $order->id,
                                'type' => 'spend',
                                'points' => $order->points_used,
                                'description' => 'Penggunaan point untuk order ' . $order->order_number,
                            ]);
                        }

                        $order->update(['points_deducted' => true]);
                    }

                    /**
                     * 🔒 STRICT STOCK DECREMENT (FIX)
                     */
                    foreach ($order->items as $item) {

                        $product = Product::lockForUpdate()->find($item->product_id);

                        if (!$product || $product->stock < $item->quantity) {
                            Log::critical('Stock inconsistency during payment', [
                                'order_id' => $order->id,
                                'product_id' => $item->product_id,
                            ]);

                            throw new \Exception('Stock inconsistency during payment');
                        }

                        $product->decrement('reserved_stock', $item->quantity);
                        $product->decrement('stock', $item->quantity);
                    }

                    /**
                     * 🔒 voucher usage (WITH GUARD)
                     */
                    /**
                     * 🔒 voucher usage (ATOMIC SAFE)
                     */
                    if ($order->voucher_id && !$order->voucher_usage_counted) {

                        $voucher = Voucher::lockForUpdate()->find($order->voucher_id);

                        if ($voucher) {

                            $affected = Voucher::where('id', $voucher->id)
                                ->where(function ($q) {
                                    $q->whereNull('usage_limit')
                                        ->orWhereColumn('usage_count', '<', 'usage_limit');
                                })
                                ->increment('usage_count');

                            if ($affected === 0) {
                                Log::warning('Voucher limit exceeded atomically', [
                                    'voucher_id' => $voucher->id,
                                    'order_id' => $order->id
                                ]);
                            }
                        }

                        $order->update([
                            'voucher_usage_counted' => true
                        ]);
                    }

                    /**
                     * ⭐ earn points
                     */
                    PointService::earn($order->user, $order);
                }
            });

            return response()->json(['message' => 'OK']);

        } catch (\Throwable $e) {

            Log::error('Midtrans webhook error', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);

            return response()->json(['message' => 'Error'], 500);
        }
    }

}
