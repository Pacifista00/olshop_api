<?php

namespace App\Http\Controllers;

use App\Models\MidtransTransaction;
use App\Models\Order;
use App\Models\Product;
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

        // 🔐 VALIDASI SIGNATURE
        if (!MidtransService::verifySignature($payload)) {
            Log::warning('Midtrans invalid signature', $payload);
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        try {

            DB::transaction(function () use ($payload) {

                // 🔒 LOCK ORDER DI DALAM TRANSACTION
                $order = Order::where('order_number', $payload['order_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    throw new \Exception('Order not found');
                }

                // Load relations setelah lock
                $order->load(['items', 'user']);

                /**
                 * =====================================================
                 * 1️⃣ SIMPAN / UPDATE DATA MIDTRANS
                 * =====================================================
                 */
                MidtransTransaction::updateOrCreate(
                    [
                        'midtrans_transaction_id' => $payload['transaction_id'],
                    ],
                    [
                        'order_id' => $order->id,
                        'status_code' => $payload['status_code'],
                        'transaction_status' => $payload['transaction_status'],
                        'payment_type' => $payload['payment_type'] ?? null,
                        'va_number' => $payload['va_numbers'][0]['va_number'] ?? null,
                        'json_data' => $payload,
                    ]
                );

                /**
                 * =====================================================
                 * 2️⃣ MAP STATUS
                 * =====================================================
                 */
                $status = MidtransService::mapPaymentStatus(
                    $payload['transaction_status'],
                    $payload['fraud_status'] ?? null
                );

                /**
                 * =====================================================
                 * 3️⃣ IDEMPOTENCY CHECK
                 * =====================================================
                 */
                if ($order->payment_status === 'paid') {
                    return;
                }

                /**
                 * =====================================================
                 * 4️⃣ UPDATE ORDER
                 * =====================================================
                 */
                $order->update([
                    'payment_status' => $status['payment_status'],
                    'status' => $status['status'],
                    'payment_method' => $payload['payment_type'] ?? null,
                    'paid_at' => $status['payment_status'] === 'paid' ? now() : null,
                    'midtrans_response' => $payload,
                ]);

                /**
                 * =====================================================
                 * 5️⃣ JIKA PAID
                 * =====================================================
                 */
                if ($status['payment_status'] === 'paid') {

                    // 🔒 Decrement stock atomik + validasi
                    foreach ($order->items as $item) {

                        $affected = Product::where('id', $item->product_id)
                            ->where('stock', '>=', $item->quantity)
                            ->decrement('stock', $item->quantity);

                        if ($affected === 0) {
                            throw new \Exception('Stock inconsistency detected');
                        }
                    }

                    // 🔒 Voucher handling
                    if ($order->voucher_id && !$order->voucher_usage_counted) {

                        $voucher = Voucher::where('id', $order->voucher_id)
                            ->lockForUpdate()
                            ->first();

                        if ($voucher) {

                            if (
                                $voucher->usage_limit &&
                                $voucher->usage_count >= $voucher->usage_limit
                            ) {
                                throw new \Exception('Voucher limit exceeded');
                            }

                            $voucher->increment('usage_count');
                        }

                        $order->update([
                            'voucher_usage_counted' => true
                        ]);
                    }

                    // ⭐ Tambah poin
                    PointService::earn($order->user, $order);
                }
            });

            return response()->json(['message' => 'OK']);

        } catch (\Exception $e) {

            Log::error('Midtrans webhook error', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);

            return response()->json(['message' => 'Error'], 500);
        }
    }

}
