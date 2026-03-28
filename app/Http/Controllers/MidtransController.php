<?php

namespace App\Http\Controllers;

use App\Jobs\CreateShipmentJob;
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

        /**
         * ✅ Logging context biar semua log rapi
         */
        Log::withContext([
            'order_id' => $payload['order_id'] ?? null,
            'transaction_id' => $payload['transaction_id'] ?? null,
        ]);

        if (!MidtransService::verifySignature($payload)) {
            Log::warning('Midtrans invalid signature', $payload);
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        /**
         * ✅ NORMALISASI STATUS
         */
        $transactionStatus = strtolower($payload['transaction_status'] ?? '');

        try {

            DB::transaction(function () use ($payload, $transactionStatus) {

                $order = Order::where('order_number', $payload['order_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $order->load(['items', 'user']);

                /**
                 * =========================================
                 * 🧠 GLOBAL IDEMPOTENCY (FINAL STATE GUARD)
                 * =========================================
                 */
                $finalStates = [
                    Order::PAYMENT_PAID,
                    Order::PAYMENT_FAILED,
                    Order::PAYMENT_EXPIRED,
                    Order::PAYMENT_CANCELLED,
                    Order::PAYMENT_REFUNDED,
                ];

                if (
                    in_array($order->payment_status, $finalStates) &&
                    $transactionStatus !== 'refund'
                ) {
                    Log::info('Webhook ignored: already final state');
                    return;
                }

                /**
                 * =========================================
                 * 💸 HANDLE REFUND
                 * =========================================
                 */
                if ($transactionStatus === 'refund') {

                    if (
                        $order->payment_status === Order::PAYMENT_REFUNDED ||
                        $order->refunded_at
                    ) {
                        Log::info('Webhook ignored: already refunded');
                        return;
                    }

                    $products = Product::lockForUpdate()
                        ->whereIn('id', $order->items->pluck('product_id'))
                        ->get()
                        ->keyBy('id');

                    foreach ($order->items as $item) {
                        $product = $products[$item->product_id] ?? null;

                        if ($product) {
                            $product->increment('stock', $item->quantity);
                        }
                    }

                    if ($order->points_used > 0 && $order->points_deducted) {

                        $userPoint = UserPoint::lockForUpdate()
                            ->where('user_id', $order->user_id)
                            ->first();

                        if ($userPoint) {
                            $userPoint->increment('total_points', $order->points_used);

                            PointHistory::create([
                                'user_id' => $order->user_id,
                                'order_id' => $order->id,
                                'type' => 'refund',
                                'points' => $order->points_used,
                                'description' => 'Refund dari webhook ' . $order->order_number,
                            ]);
                        }

                        $order->update(['points_deducted' => false]);
                    }

                    $refund = $payload['refunds'][0] ?? null;
                    $bankConfirmedAt = $refund['bank_confirmed_at'] ?? null;

                    $order->update([
                        'payment_status' => Order::PAYMENT_REFUNDED,
                        'status' => Order::STATUS_CANCELLED,
                        'refunded_at' => $bankConfirmedAt ?? now(),
                        'bank_confirmed_at' => $bankConfirmedAt,
                    ]);

                    Log::info('Webhook refund processed');

                    return;
                }

                /**
                 * =========================================
                 * 🔍 GROSS VALIDATION
                 * =========================================
                 */
                if (bccomp((string) $payload['gross_amount'], (string) $order->total_amount, 2) !== 0) {

                    Log::critical('Gross amount mismatch', [
                        'midtrans' => $payload['gross_amount'],
                        'local' => $order->total_amount,
                    ]);

                    return;
                }

                /**
                 * =========================================
                 * 💾 SAVE TRANSACTION
                 * =========================================
                 */
                MidtransTransaction::updateOrCreate(
                    ['midtrans_transaction_id' => $payload['transaction_id']],
                    [
                        'order_id' => $order->id,
                        'status_code' => $payload['status_code'],
                        'transaction_status' => $transactionStatus,
                        'payment_type' => $payload['payment_type'] ?? null,
                        'va_number' => $payload['va_numbers'][0]['va_number']
                            ?? ($payload['permata_va_number'] ?? null),
                        'json_data' => $payload,
                    ]
                );

                $status = MidtransService::mapPaymentStatus(
                    $transactionStatus,
                    $payload['fraud_status'] ?? null
                );

                if ($status['payment_status'] === 'refunded') {

                    if ($order->refunded_at) {
                        Log::info('Refund already processed', [
                            'order_id' => $order->id
                        ]);
                        return;
                    }
                }

                /**
                 * =========================================
                 * 🚫 PREVENT STATUS DOWNGRADE
                 * =========================================
                 */
                $priority = [
                    'pending' => 1,
                    'paid' => 2,
                    'failed' => 2,
                    'expired' => 2,
                    'cancelled' => 2,
                    'refunded' => 3,
                ];

                if (
                    isset($priority[$order->payment_status]) &&
                    isset($priority[$status['payment_status']]) &&
                    $priority[$status['payment_status']] < $priority[$order->payment_status]
                ) {
                    Log::warning('Ignored status downgrade', [
                        'from' => $order->payment_status,
                        'to' => $status['payment_status']
                    ]);
                    return;
                }

                $wasPaid = $order->payment_status === Order::PAYMENT_PAID;

                /**
                 * =========================================
                 * 🔄 UPDATE ORDER
                 * =========================================
                 */
                $order->update([
                    'payment_status' => $status['payment_status'],
                    'status' => $status['status'],
                    'payment_method' => $payload['payment_type'] ?? null,
                    'paid_at' => $status['payment_status'] === 'paid'
                        ? ($order->paid_at ?? now())
                        : $order->paid_at,
                    'midtrans_response' => $payload,
                ]);

                /**
                 * =========================================
                 * 🔓 RELEASE RESERVED STOCK
                 * =========================================
                 */
                if (
                    !$wasPaid &&
                    in_array($status['payment_status'], ['failed', 'expired', 'cancelled'])
                ) {

                    $products = Product::lockForUpdate()
                        ->whereIn('id', $order->items->pluck('product_id'))
                        ->get()
                        ->keyBy('id');

                    foreach ($order->items as $item) {

                        $product = $products[$item->product_id] ?? null;

                        if (!$product)
                            continue;

                        $product->update([
                            'reserved_stock' => max(0, $product->reserved_stock - $item->quantity)
                        ]);
                    }
                }

                /**
                 * =========================================
                 * 💰 BUSINESS EFFECT
                 * =========================================
                 */
                if (!$wasPaid && $status['payment_status'] === 'paid') {

                    $products = Product::lockForUpdate()
                        ->whereIn('id', $order->items->pluck('product_id'))
                        ->get()
                        ->keyBy('id');

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

                    foreach ($order->items as $item) {

                        $product = $products[$item->product_id] ?? null;

                        if (!$product || $product->stock < $item->quantity) {
                            Log::critical('Stock inconsistency during payment', [
                                'product_id' => $item->product_id,
                            ]);

                            throw new \Exception('Stock inconsistency during payment');
                        }

                        $product->update([
                            'reserved_stock' => max(0, $product->reserved_stock - $item->quantity),
                            'stock' => $product->stock - $item->quantity
                        ]);
                    }

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
                                Log::warning('Voucher limit exceeded');
                            }
                        }

                        $order->update(['voucher_usage_counted' => true]);
                    }

                    PointService::earn($order->user, $order);

                    /**
                     * 🚚 SAFE AFTER COMMIT
                     */
                    DB::afterCommit(function () use ($order) {
                        if (!$order->tracking_number) {
                            CreateShipmentJob::dispatch($order->id);
                        }
                    });
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
