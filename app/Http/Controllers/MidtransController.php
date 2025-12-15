<?php

namespace App\Http\Controllers;

use App\Models\MidtransTransaction;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MidtransController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();

        // Validasi signature
        $signatureKey = hash(
            'sha512',
            $payload['order_id'] .
            $payload['status_code'] .
            $payload['gross_amount'] .
            config('midtrans.server_key')
        );

        if ($signatureKey !== $payload['signature_key']) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $order = Order::where('order_number', $payload['order_id'])->firstOrFail();

        DB::transaction(function () use ($order, $payload) {

            // Simpan histori midtrans
            MidtransTransaction::create([
                'order_id' => $order->id,
                'midtrans_transaction_id' => $payload['transaction_id'],
                'status_code' => $payload['status_code'],
                'transaction_status' => $payload['transaction_status'],
                'payment_type' => $payload['payment_type'],
                'va_number' => $payload['va_numbers'][0]['va_number'] ?? null,
                'json_data' => $payload
            ]);

            match ($payload['transaction_status']) {
                'capture', 'settlement' => $order->update([
                    'payment_status' => 'paid',
                    'status' => 'processing'
                ]),
                'pending' => $order->update([
                    'payment_status' => 'pending'
                ]),
                'expire' => $order->update([
                    'payment_status' => 'expired'
                ]),
                'cancel', 'deny' => $order->update([
                    'payment_status' => 'failed'
                ]),
            };
        });

        return response()->json(['message' => 'OK']);
    }
}
