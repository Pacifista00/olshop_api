<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Services\BiteshipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BiteshipController extends Controller
{
    public function previewShipping(Request $request)
    {
        try {
            $user = auth()->user();

            $cart = Cart::where('user_id', $user->id)
                ->with('items.product')
                ->first();

            if (!$cart || $cart->items->isEmpty()) {
                return response()->json([
                    'message' => 'Keranjang kosong'
                ], 400);
            }

            $addresses = Address::where('user_id', $user->id)->get();

            // 1️⃣ Belum ada alamat sama sekali
            if ($addresses->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Alamat belum ditambahkan. Silakan tambahkan alamat terlebih dahulu.'
                ], 400);
            }

            // 2️⃣ Sudah ada alamat tapi belum ada yang default
            $defaultAddress = $addresses->firstWhere('is_default', true);

            if (!$defaultAddress) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Alamat utama belum ditentukan. Silakan pilih salah satu alamat sebagai alamat utama.'
                ], 400);
            }

            // 3️⃣ Alamat default ditemukan → lanjut proses
            $address = $defaultAddress;

            if (!$address->biteship_location_id) {
                return response()->json([
                    'message' => 'Alamat belum valid'
                ], 400);
            }

            $items = [];

            foreach ($cart->items as $item) {
                if (!$item->product) {
                    continue;
                }

                $product = $item->product;

                $payload = [
                    'name' => $product->name,
                    'value' => (int) $product->price,
                    'quantity' => $item->quantity,
                    'weight' => max(1, (int) $product->weight),
                ];

                // 🔥 kirim dimensi HANYA jika produk bulky
                if (!empty($product->use_dimension)) {
                    $payload['length'] = max(1, (int) $product->length);
                    $payload['width'] = max(1, (int) $product->width);
                    $payload['height'] = max(1, (int) $product->height);
                }

                $items[] = $payload;
            }

            if (empty($items)) {
                return response()->json([
                    'message' => 'Produk tidak valid'
                ], 400);
            }

            $rates = BiteshipService::getRates([
                'origin_area_id' => config('services.biteship.origin'),
                'destination_area_id' => $address->biteship_location_id,
                'couriers' => 'jne,jnt',
                'items' => $items,
            ]);

            return response()->json([
                'shipping_options' => $rates['pricing'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal mengambil ongkir',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function handle(Request $request)
    {
        $payload = $request->all();

        $event = $request->input('event');
        $orderId = $request->input('order_id');
        $status = $request->input('status');

        Log::info('Biteship webhook', [
            'order_id' => $orderId,
            'status' => $status
        ]);

        if ($event !== 'order.status') {
            return response()->json(['message' => 'Event ignored']);
        }

        if (!$orderId || !$status) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $order = Order::where('biteship_order_id', $orderId)->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->shipping_status === Order::SHIPPING_DELIVERED) {
            return response()->json(['message' => 'Already delivered']);
        }

        DB::transaction(function () use ($order, $request, $payload, $status) {

            $data = [
                'tracking_number' => $request->input('courier_waybill_id'),
                'courier' => $request->input('courier_company'),
                'courier_service' => $request->input('courier_type'),
                'shipping_status' => $status,
                'shipment_response' => $payload,
            ];

            $statusMap = [
                'cancelled' => Order::STATUS_CANCELLED,
                'delivered' => Order::STATUS_COMPLETED,
                'returned' => Order::STATUS_RETURNED,
                'disposed' => Order::STATUS_DISPOSED,
            ];

            if (isset($statusMap[$status])) {
                $data['status'] = $statusMap[$status];
            }

            $order->update($data);
        });

        return response()->json(['success' => true]);
    }
    public function handleWaybill(Request $request)
    {
        $event = $request->input('event');
        $orderId = $request->input('order_id');
        $waybill = $request->input('courier_waybill_id');

        if (!$event) {
            return response()->json(['ok' => true]);
        }

        if ($event !== 'order.waybill_id') {
            return response()->json(['message' => 'Event ignored']);
        }

        if (!$orderId) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        Log::info('Biteship waybill webhook', [
            'order_id' => $orderId,
            'waybill' => $waybill
        ]);

        DB::transaction(function () use ($orderId, $request, $waybill) {

            $order = Order::where('biteship_order_id', $orderId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                return;
            }

            // idempotent guard
            if ($order->tracking_number === $waybill) {
                return;
            }

            $order->update([
                'tracking_number' => $waybill,
                'shipping_status' => $request->input('status'),
                'shipment_response' => $request->all(),
            ]);
        });

        return response()->json(['success' => true]);
    }
    public function handlePrice(Request $request)
    {
        $event = $request->input('event');
        $orderId = $request->input('order_id');

        if (!$event) {
            return response()->json(['ok' => true]);
        }

        if ($event !== 'order.price') {
            return response()->json(['message' => 'Event ignored']);
        }

        if (!$orderId) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $order = Order::where('biteship_order_id', $orderId)->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->status === Order::STATUS_COMPLETED) {
            return response()->json(['message' => 'Order completed']);
        }

        $price = (int) $request->input('price');

        Log::info('Biteship price webhook', [
            'order_id' => $orderId,
            'price' => $price
        ]);

        DB::transaction(function () use ($order, $request, $price) {

            $order = Order::lockForUpdate()->find($order->id);

            // idempotent guard
            if ($order->shipping_cost == $price) {
                return;
            }

            $order->update([
                'shipping_cost' => $price,
                'tracking_number' => $request->input('courier_waybill_id'),
                'shipping_status' => $request->input('status'),
                'shipment_response' => $request->all(),
            ]);

        });

        return response()->json(['success' => true]);
    }

}
