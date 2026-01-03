<?php

namespace App\Http\Controllers;

use App\Models\MidtransTransaction;
use App\Models\Order;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MidtransController extends Controller
{
    public function handle(Request $request)
    {
        MidtransService::init();

        $payload = $request->all();

        // 🔐 VALIDASI SIGNATURE
        if (!MidtransService::verifySignature($payload)) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $order = Order::where('order_number', $payload['order_id'])->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        DB::transaction(function () use ($order, $payload) {

            $status = MidtransService::mapPaymentStatus(
                $payload['transaction_status'],
                $payload['fraud_status'] ?? null
            );

            $order->update(array_merge($status, [
                'payment_method' => $payload['payment_type'] ?? null,
                'paid_at' => in_array($status['payment_status'], ['paid']) ? now() : null,
                'midtrans_response' => $payload,
            ]));

            // 🔄 ROLLBACK STOK JIKA GAGAL
            if ($status['payment_status'] === 'paid') {

                $cart = $order->user->cart;

                if ($cart) {
                    $cart->items()->delete(); // hapus item cart
                    $cart->delete();          // hapus cart
                }
            }

            // 🔄 JIKA GAGAL → ROLLBACK STOK
            if ($status['payment_status'] === 'failed') {
                foreach ($order->items as $item) {
                    $item->product->increment('stock', $item->quantity);
                }
            }
        });

        return response()->json(['message' => 'OK']);
    }
}
