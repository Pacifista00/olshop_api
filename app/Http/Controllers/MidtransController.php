<?php

namespace App\Http\Controllers;

use App\Models\MidtransTransaction;
use App\Models\Order;
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

        // 🔎 Cari order berdasarkan order_number (Midtrans order_id)
        $order = Order::with(['items.product', 'user.cart.items'])
            ->where('order_number', $payload['order_id'])
            ->first();

        if (!$order) {
            Log::warning('Order not found', ['order_id' => $payload['order_id']]);
            return response()->json(['message' => 'Order not found'], 404);
        }

        DB::transaction(function () use ($order, $payload) {

            /**
             * =====================================================
             * 1️⃣ SIMPAN / UPDATE DATA MIDTRANS (AUDIT TRAIL)
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
             * 2️⃣ MAP STATUS MIDTRANS → STATUS SISTEM
             * =====================================================
             */
            $status = MidtransService::mapPaymentStatus(
                $payload['transaction_status'],
                $payload['fraud_status'] ?? null
            );

            /**
             * =====================================================
             * 3️⃣ IDEMPOTENCY (JANGAN ULANGI SIDE EFFECT)
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
                'payment_status' => $status['payment_status'], // paid / pending / failed
                'status' => $status['status'],                 // processing / canceled
                'payment_method' => $payload['payment_type'] ?? null,
                'paid_at' => $status['payment_status'] === 'paid' ? now() : null,
                'midtrans_response' => $payload,
            ]);

            /**
             * =====================================================
             * 5️⃣ JIKA PEMBAYARAN SUKSES
             * =====================================================
             */
            if ($status['payment_status'] === 'paid') {

                // 📦 Kurangi stok (FINAL)
                foreach ($order->items as $item) {
                    $item->product->decrement('stock', $item->quantity);
                }

                // ⭐ Tambah poin (IDEMPOTENT DI DALAM SERVICE)
                PointService::earn($order->user, $order);

                // 🛒 Hapus cart
                if ($order->user && $order->user->cart) {
                    $order->user->cart->items()->delete();
                    $order->user->cart()->delete();
                }
            }
        });

        return response()->json(['message' => 'OK']);
    }
}
