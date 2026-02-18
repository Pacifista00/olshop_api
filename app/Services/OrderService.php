<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\BiteshipService;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public static function checkoutFromCart(
        $user,
        string $courierCode,
        string $courierServiceCode,
        int $shippingPrice,
        ?string $voucherCode = null
    ): Order {
        return DB::transaction(function () use ($user, $courierCode, $courierServiceCode, $shippingPrice, $voucherCode) {

            // 1️⃣ Cart
            $cart = Cart::where('user_id', $user->id)
                ->with('items.product')
                ->firstOrFail();

            if ($cart->items->isEmpty()) {
                throw new \Exception('Keranjang kosong');
            }

            // 2️⃣ Address
            $address = Address::where('user_id', $user->id)
                ->where('is_default', true)
                ->first();

            if (!$address) {
                throw new \Exception('Alamat default belum diset');
            }

            $subtotal = 0;
            $products = [];

            // 3️⃣ Subtotal + lock stok
            foreach ($cart->items as $cartItem) {
                $product = Product::lockForUpdate()
                    ->findOrFail($cartItem->product_id);

                if ($product->stock < $cartItem->quantity) {
                    throw new \Exception("Stok {$product->name} tidak cukup");
                }

                $products[$product->id] = $product;
                $subtotal += $product->price * $cartItem->quantity;
            }

            // 4️⃣ Voucher (OPSIONAL)
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

                // Hitung diskon
                $voucherDiscount = $voucher->type === 'percentage'
                    ? $subtotal * ($voucher->value / 100)
                    : $voucher->value;

                if ($voucher->max_discount) {
                    $voucherDiscount = min($voucherDiscount, $voucher->max_discount);
                }
            }

            // 5️⃣ Ongkir (Biteship)
            $biteshipItems = [];

            foreach ($cart->items as $cartItem) {
                $product = $products[$cartItem->product_id];

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

            $shippingCost = (int) $service['price'];

            // 6️⃣ Total FINAL
            $total = max(0, $subtotal - $voucherDiscount + $shippingCost);

            // 7️⃣ Create order
            $order = Order::create([
                'user_id' => $user->id,
                'shipping_address_id' => $address->id,
                'customer_name' => $address->recipient_name,
                'customer_phone' => $address->phone,
                'shipping_address_snapshot' => [
                    'recipient_name' => $address->recipient_name,
                    'phone' => $address->phone,
                    'full_address' => $address->full_address,
                    'province' => $address->province,
                    'city' => $address->city,
                    'postal_code' => $address->postal_code,
                ],
                'order_number' => 'ORD-' . now()->format('YmdHis') . '-' . Str::random(6),

                'subtotal_amount' => $subtotal,
                'voucher_id' => $voucher?->id,
                'voucher_discount' => $voucherDiscount,

                'shipping_cost' => $shippingCost,
                'total_amount' => $total,

                'courier' => $courierCode,
                'courier_service' => strtoupper($courierServiceCode),

                'payment_status' => Order::PAYMENT_UNPAID,
                'status' => Order::STATUS_CREATED,
            ]);

            // 8️⃣ Order items
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

            // 9️⃣ Increment usage voucher
            if ($voucher) {
                $voucher->increment('usage_count');
            }

            // Logging
            Log::info('Checkout success', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'total' => $total,
            ]);

            return $order;
        });
    }
}
