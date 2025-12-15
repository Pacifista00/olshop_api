<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Services\OrderService;
use App\Services\MidtransService;

class OrderController extends Controller
{
    public function checkout(CheckoutRequest $request)
    {
        $user = auth()->user();

        try {
            $order = OrderService::checkoutFromCart(
                $request->shipping_address_id,
                $user
            );

            $snapToken = MidtransService::createSnapToken([
                'transaction_details' => [
                    'order_id' => $order->order_number,
                    'gross_amount' => (int) $order->total_amount
                ],
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email
                ]
            ]);

            $order->update(['midtrans_snap_token' => $snapToken]);

            return response()->json(compact('snapToken'));

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
