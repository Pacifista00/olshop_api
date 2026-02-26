<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\MidtransService;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function orders()
    {
        $orders = Order::with(['items.product'])
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
    public function index(Request $request)
    {
        $query = Order::with(['items.product'])
            ->where('user_id', auth()->id());

        // ✅ SUPPORT SINGLE & MULTIPLE STATUS
        if ($request->filled('status')) {
            $statuses = is_array($request->status)
                ? $request->status
                : [$request->status];

            $query->whereIn('status', $statuses);
        }

        $orders = $query->orderByDesc('created_at')->paginate(10);

        return response()->json([
            'status' => 'success',
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

            /**
             * =========================================
             * 1️⃣ VALIDASI ONGKIR (OUTSIDE TX)
             * =========================================
             */
            $shippingData = OrderService::validateShippingCost(
                $user,
                $request->courier_code,
                $request->courier_service_code,
                $request->shipping_price
            );

            /**
             * =========================================
             * 2️⃣ CREATE ORDER
             * =========================================
             */
            $order = OrderService::checkoutFromCart(
                $user,
                $shippingData,
                $request->voucher_code,
                (int) ($request->input('points_used') ?? 0)
            );

            /**
             * =========================================
             * ⭐ IDPOTENT SNAP TOKEN CHECK
             * =========================================
             */
            $order = DB::transaction(function () use ($order) {
                return Order::lockForUpdate()->find($order->id);
            });

            if ($order->midtrans_snap_token) {
                return response()->json([
                    'snapToken' => $order->midtrans_snap_token
                ]);
            }

            /**
             * =========================================
             * 3️⃣ CREATE SNAP TOKEN (RETRY SAFE)
             * =========================================
             */
            $snapToken = retry(3, function () use ($order, $user) {

                return MidtransService::createSnapToken([
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

            }, 200);

            /**
             * =========================================
             * 🔒 RETRY SAVE TOKEN (ANTI ORPHAN)
             * =========================================
             */
            retry(3, function () use ($order, $snapToken) {

                DB::transaction(function () use ($order, $snapToken) {

                    $fresh = Order::lockForUpdate()->find($order->id);

                    if (!$fresh->midtrans_snap_token) {
                        $fresh->update([
                            'midtrans_snap_token' => $snapToken
                        ]);
                    }

                });

            }, 100);

            return response()->json(compact('snapToken'));

        } catch (\Throwable $e) {

            Log::error('Checkout error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null
            ]);

            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }
    public function retry(Order $order)
    {
        $user = auth()->user();

        try {
            $snapToken = OrderService::retryPayment($order, $user);

            return response()->json([
                'snapToken' => $snapToken
            ]);

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
        $user = auth()->user();

        $query = Order::with(['items.product'])
            ->where('id', $id);

        // 🔥 jika bukan admin/developer → batasi
        if (!in_array($user->role, ['admin', 'developer'])) {
            $query->where('user_id', $user->id);
        }

        $order = $query->first();

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
    public function pack(Order $order)
    {
        $order->update([
            'status' => Order::STATUS_PACKED
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Order packed successfully'
        ]);
    }
}
