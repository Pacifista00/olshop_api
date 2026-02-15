<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\MidtransService;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with(['items.product'])
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'List of orders retrieved successfully.',
            'data' => OrderResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function checkout(CheckoutRequest $request)
    {
        $user = auth()->user();

        try {
            $order = OrderService::checkoutFromCart(
                $user,
                $request->courier_code,
                $request->courier_service_code,
                $request->shipping_price,
                $request->voucher_code
            );

            $snapToken = MidtransService::createSnapToken([
                'transaction_details' => [
                    'order_id' => $order->order_number,
                    'gross_amount' => (int) $order->total_amount
                ],
                'callbacks' => [
                    'finish' => config('app.frontend_url') . '/after-payment',
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
    public function showByNumber($orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        return response()->json([
            'data' => $order
        ]);
    }
    public function show($id)
    {
        $order = Order::with(['items.product'])
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail order retrieved successfully.',
            'data' => new OrderResource($order)
        ]);
    }
}
