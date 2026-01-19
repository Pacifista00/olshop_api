<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
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
            ->get();

        return response()->json([
            'data' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'payment_status' => $order->payment_status,

                    'created_at' => $order->created_at->toISOString(),
                    'created_at_formatted' => $order->created_at->format('d M Y H:i'),

                    'subtotal_amount' => $order->subtotal_amount,
                    'shipping_cost' => $order->shipping_cost,
                    'voucher_discount' => $order->voucher_discount,
                    'total_amount' => $order->total_amount,

                    'courier' => [
                        'code' => $order->courier,
                        'service' => $order->courier_service,
                        'etd' => $order->courier_etd,
                    ],

                    'items' => $order->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'total_price' => $item->quantity * $item->unit_price,
                            'product' => [
                                'name' => $item->product->name,
                                'image_url' => asset('storage/' . $item->product->image),
                            ],
                        ];
                    }),
                ];
            })
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
                'message' => 'Order tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'payment_status' => $order->payment_status,

                'created_at' => $order->created_at->toISOString(),
                'created_at_formatted' => $order->created_at->format('d M Y H:i'),

                'subtotal_amount' => $order->subtotal_amount,
                'shipping_cost' => $order->shipping_cost,
                'voucher_discount' => $order->voucher_discount,
                'total_amount' => $order->total_amount,

                'courier' => [
                    'code' => $order->courier,
                    'service' => $order->courier_service,
                    'etd' => $order->courier_etd,
                ],

                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->quantity * $item->unit_price,
                        'product' => [
                            'name' => $item->product->name,
                            'image_url' => asset('storage/' . $item->product->image),
                        ],
                    ];
                }),
            ]
        ]);
    }
}
