<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Jobs\ProcessRefundJob;
use App\Models\Order;
use App\Models\PointHistory;
use App\Models\Product;
use App\Models\UserPoint;
use App\Services\BiteshipService;
use App\Services\OrderService;
use App\Services\MidtransService;
use App\Jobs\CancelBiteshipOrderJob;
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

        $status = $request->status;

        $statusMap = [

            'created' => [
                'order' => ['created', 'pending'],
                'shipping' => []
            ],

            'packed' => [
                'order' => ['processing', 'packed'],
                'shipping' => []
            ],

            'shipped' => [
                'order' => ['shipped'],
                'shipping' => [
                    'allocated',
                    'picking_up',
                    'picked',
                    'on_hold',
                    'return_in_transit',
                    'dropping_off'
                ]
            ],

            'completed' => [
                'order' => [
                    'cancelled',
                    'completed',
                    'returned',
                    'disposed'
                ],
                'shipping' => [
                    'delivered',
                    'returned',
                    'disposed',
                    'courier_not_found',
                    'cancelled'
                ],
                'payment' => [
                    'failed',
                    'expired',
                    'cancelled'
                ]

            ]
        ];

        if ($status && $status !== 'all' && isset($statusMap[$status])) {

            $orderStatuses = $statusMap[$status]['order'];
            $shippingStatuses = $statusMap[$status]['shipping'];
            $paymentStatuses = $statusMap[$status]['payment'] ?? [];

            $excludedPaymentStatuses = ['failed', 'expired', 'cancelled'];

            $query->where(function ($q) use ($orderStatuses, $shippingStatuses, $paymentStatuses, $excludedPaymentStatuses, $status) {

                if ($status === 'created') {
                    $q->whereIn('status', $orderStatuses)
                        ->whereNotIn('payment_status', $excludedPaymentStatuses);
                } elseif ($status === 'packed') {
                    $q->whereIn('status', $orderStatuses)
                        ->whereNull('shipping_status')
                        ->whereNotIn('payment_status', $excludedPaymentStatuses);
                } elseif ($status === 'shipped') {
                    $q->whereNotIn('payment_status', $excludedPaymentStatuses)
                        ->where(function ($qq) use ($orderStatuses, $shippingStatuses) {
                            $qq->whereIn('shipping_status', $shippingStatuses)
                                ->orWhereIn('status', $orderStatuses);
                        });
                } elseif ($status === 'completed') {
                    $q->where(function ($qq) use ($orderStatuses, $shippingStatuses, $paymentStatuses) {
                        $qq->whereIn('shipping_status', $shippingStatuses)
                            ->orWhereIn('status', $orderStatuses)
                            ->orWhereIn('payment_status', $paymentStatuses);
                    });
                }
            });
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
                    ],
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

            return response()->json([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'snapToken' => $snapToken
            ]);

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
    public function cancel($orderId)
    {
        $user = auth()->user();

        try {
            DB::transaction(function () use ($orderId, $user) {

                $order = Order::where('id', $orderId)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($order->status === Order::STATUS_CANCELLED) {
                    throw new \Exception('Pesanan sudah dibatalkan');
                }

                if ($order->payment_status === Order::PAYMENT_REFUND_PENDING) {
                    throw new \Exception('Refund sedang diproses');
                }

                /**
                 * 💰 SUDAH BAYAR → REFUND FLOW
                 */
                if ($order->payment_status === Order::PAYMENT_PAID) {

                    if ($order->shipping_status) {
                        throw new \Exception('Pesanan sudah diproses/dikirim');
                    }

                    $order->update([
                        'status' => Order::STATUS_CANCELLED,
                        'payment_status' => Order::PAYMENT_REFUND_PENDING
                    ]);

                    // 🚀 Queue setelah commit
                    CancelBiteshipOrderJob::dispatch($order->id)->afterCommit();
                    ProcessRefundJob::dispatch($order->id)->afterCommit();

                    return;
                }

                /**
                 * 🧾 BELUM BAYAR → langsung cancel
                 */
                if (
                    !in_array($order->status, [
                        Order::STATUS_CREATED,
                        Order::STATUS_PENDING
                    ])
                ) {
                    throw new \Exception('Pesanan tidak bisa dibatalkan');
                }

                $order->update([
                    'status' => Order::STATUS_CANCELLED,
                    'payment_status' => Order::PAYMENT_FAILED
                ]);
            });

            return response()->json([
                'message' => 'Pesanan berhasil dibatalkan'
            ]);

        } catch (\Throwable $e) {

            Log::error('Cancel order error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null
            ]);

            return response()->json([
                'message' => $e->getMessage()
            ], 422);
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
