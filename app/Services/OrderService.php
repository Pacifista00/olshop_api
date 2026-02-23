<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PointHistory;
use App\Models\Product;
use App\Models\UserPoint;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\BiteshipService;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public static function checkoutFromCart(
        $user,
        array $shippingData,
        ?string $voucherCode = null,
        int $pointsUsed = 0
    ): Order {

        return DB::transaction(function () use ($user, $shippingData, $voucherCode, $pointsUsed) {

            if (empty($shippingData['address'])) {
                throw new \Exception('Alamat tidak valid');
            }

            $cart = Cart::where('user_id', $user->id)
                ->with('items.product')
                ->lockForUpdate()
                ->firstOrFail();

            if ($cart->items->isEmpty()) {
                throw new \Exception('Keranjang kosong');
            }

            if ($pointsUsed < 0) {
                throw new \Exception('Point tidak valid');
            }

            /**
             * =========================================
             * POINT CHECK
             * =========================================
             */
            $userPoint = UserPoint::lockForUpdate()
                ->where('user_id', $user->id)
                ->first();

            $availablePoints = $userPoint?->total_points ?? 0;

            if ($pointsUsed > $availablePoints) {
                throw new \Exception('Point tidak mencukupi');
            }

            /**
             * =========================================
             * SUBTOTAL + STOCK CHECK
             * =========================================
             */
            $subtotal = 0;
            $products = [];

            foreach ($cart->items as $cartItem) {

                $product = Product::lockForUpdate()
                    ->findOrFail($cartItem->product_id);

                $availableStock = $product->stock - $product->reserved_stock;

                if ($availableStock < $cartItem->quantity) {
                    throw new \Exception("Stok {$product->name} tidak cukup");
                }

                $product->increment('reserved_stock', $cartItem->quantity);

                $products[$product->id] = $product;
                $subtotal += $product->price * $cartItem->quantity;
            }

            /**
             * =========================================
             * VOUCHER VALIDATION
             * =========================================
             */
            $voucher = null;
            $voucherDiscount = 0;

            if ($voucherCode) {

                $voucher = Voucher::lockForUpdate()
                    ->where('code', $voucherCode)
                    ->first();

                if (!$voucher || !$voucher->is_active) {
                    throw new \Exception('Voucher tidak valid');
                }

                if ($voucher->starts_at && now()->lt($voucher->starts_at)) {
                    throw new \Exception('Voucher belum berlaku');
                }

                if ($voucher->expires_at && now()->gt($voucher->expires_at)) {
                    throw new \Exception('Voucher sudah kadaluarsa');
                }

                if ($voucher->min_order_amount && $subtotal < $voucher->min_order_amount) {
                    throw new \Exception('Minimal order tidak memenuhi syarat voucher');
                }

                if ($voucher->usage_limit && $voucher->usage_count >= $voucher->usage_limit) {
                    throw new \Exception('Voucher sudah habis');
                }

                $voucherDiscount = $voucher->type === 'percentage'
                    ? (int) floor($subtotal * ($voucher->value / 100))
                    : (int) $voucher->value;

                if ($voucher->max_discount) {
                    $voucherDiscount = min($voucherDiscount, $voucher->max_discount);
                }
            }

            /**
             * =========================================
             * POINT CALCULATION
             * =========================================
             */
            /**
             * =========================================
             * POINT CALCULATION (FIXED)
             * =========================================
             */
            $pointValue = 5000;

            // berapa poin maksimal yang boleh dipakai berdasarkan total
            $maxPointCanUse = (int) floor(
                max(0, $subtotal - $voucherDiscount + $shippingData['shipping_cost'])
                / $pointValue
            );

            // clamp request user
            $actualPointsUsed = min($pointsUsed, $maxPointCanUse);

            // hitung diskon FINAL dari poin yang benar-benar dipakai
            $pointDiscount = $actualPointsUsed * $pointValue;

            if ($pointsUsed > 0 && $actualPointsUsed === 0) {
                throw new \Exception('Point tidak mencukupi untuk digunakan');
            }

            /**
             * =========================================
             * FINAL TOTAL
             * =========================================
             */
            $total = max(
                0,
                $subtotal - $voucherDiscount - $pointDiscount + $shippingData['shipping_cost']
            );

            /**
             * =========================================
             * CREATE ORDER
             * =========================================
             */
            $order = Order::create([
                'user_id' => $user->id,
                'shipping_address_id' => $shippingData['address']->id,
                'customer_name' => $shippingData['address']->recipient_name,
                'customer_phone' => $shippingData['address']->phone,
                'shipping_address_snapshot' => $shippingData['address']->toArray(),

                'order_number' => 'ORD-' . Str::uuid(),

                'subtotal_amount' => $subtotal,
                'points_used' => $actualPointsUsed,
                'points_discount' => $pointDiscount,
                'points_deducted' => false,

                'voucher_id' => $voucher?->id,
                'voucher_discount' => $voucherDiscount,
                'voucher_usage_counted' => false,

                'shipping_cost' => $shippingData['shipping_cost'],
                'total_amount' => $total,

                'courier' => $shippingData['courier_code'],
                'courier_service' => $shippingData['courier_service_code'],

                'payment_status' => Order::PAYMENT_UNPAID,
                'status' => Order::STATUS_CREATED,
            ]);

            /**
             * ORDER ITEMS
             */
            foreach ($cart->items as $cartItem) {
                $product = $products[$cartItem->product_id];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $product->price,
                    'subtotal' => $product->price * $cartItem->quantity,
                ]);
            }

            /**
             * CLEAR CART
             */
            $cart->items()->delete();

            return $order;
        });
    }
    public static function validateShippingCost(
        $user,
        string $courierCode,
        string $courierServiceCode,
        int $shippingPrice
    ): array {

        $address = Address::where('user_id', $user->id)
            ->where('is_default', true)
            ->first();

        if (!$address) {
            throw new \Exception('Alamat default belum diset');
        }

        $cart = Cart::where('user_id', $user->id)
            ->with('items.product')
            ->firstOrFail();

        if ($cart->items->isEmpty()) {
            throw new \Exception('Keranjang kosong');
        }

        $biteshipItems = [];

        foreach ($cart->items as $cartItem) {
            $product = $cartItem->product;

            $biteshipItems[] = [
                'name' => $product->name,
                'value' => (int) $product->price,
                'quantity' => $cartItem->quantity,
                'weight' => max(1, (int) $product->weight),
            ];
        }

        $rates = BiteshipService::getRates([
            'origin_area_id' => config('services.biteship.origin'),
            'destination_area_id' => $address->biteship_location_id,
            'couriers' => 'jne,jnt,sicepat',
            'items' => $biteshipItems,
        ]);

        $pricing = collect($rates['pricing'] ?? []);

        if ($pricing->isEmpty()) {
            throw new \Exception('Tidak ada pricing dari Biteship');
        }

        $service = $pricing
            ->where('courier_code', $courierCode)
            ->first(
                fn($item) =>
                strtolower($item['courier_service_code']) === strtolower($courierServiceCode)
            );

        if (!$service) {
            throw new \Exception('Service pengiriman tidak tersedia');
        }

        if ((int) $service['price'] !== (int) $shippingPrice) {
            throw new \Exception('Harga ongkir berubah');
        }

        return [
            'address' => $address,
            'shipping_cost' => (int) $service['price'],
            'courier_code' => $courierCode,
            'courier_service_code' => strtoupper($courierServiceCode),
        ];
    }
    public static function retryPayment(Order $order, $user): string
    {
        return DB::transaction(function () use ($order, $user) {

            $order = Order::where('id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->user_id !== $user->id) {
                throw new \Exception('Order tidak ditemukan');
            }

            if ($order->payment_status !== Order::PAYMENT_UNPAID) {
                throw new \Exception('Order sudah dibayar');
            }

            if ($order->created_at->addHours(1)->isPast()) {
                throw new \Exception('Order sudah expired');
            }

            // ✅ Idempotent: kalau sudah ada token, return saja
            if ($order->midtrans_snap_token) {
                return $order->midtrans_snap_token;
            }

            // Generate token
            $snapToken = MidtransService::createSnapToken([
                'transaction_details' => [
                    'order_id' => $order->order_number,
                    'gross_amount' => (int) $order->total_amount
                ],
                'customer_details' => [
                    'first_name' => $order->customer_name,
                    'email' => $user->email
                ]
            ]);

            $order->update([
                'midtrans_snap_token' => $snapToken
            ]);

            return $snapToken;
        });
    }


}
