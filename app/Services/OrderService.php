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
                $voucher = Voucher::where('code', $voucherCode)
                    ->lockForUpdate()
                    ->first();

                if (!$voucher) {
                    throw new \Exception('Voucher tidak ditemukan');
                }

                if (!$voucher->is_active) {
                    throw new \Exception('Voucher tidak aktif');
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
                if ($voucher->type === 'percentage') {
                    $voucherDiscount = $subtotal * ($voucher->value / 100);
                } else {
                    $voucherDiscount = $voucher->value;
                }

                if ($voucher->max_discount) {
                    $voucherDiscount = min($voucherDiscount, $voucher->max_discount);
                }
            }

            // 5️⃣ Ongkir
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
                'couriers' => 'jne,jnt,sicepat', // 🔥 SAMA DENGAN PREVIEW
                'items' => $biteshipItems,
            ]);

            $pricing = collect($rates['pricing'] ?? []);

            if ($pricing->isEmpty()) {
                throw new \Exception('Tidak ada pricing dari Biteship');
            }

            $byCourier = $pricing->where('courier_code', $courierCode);

            if ($byCourier->isEmpty()) {
                throw new \Exception("Kurir {$courierCode} tidak tersedia");
            }

            $byService = $byCourier->first(
                fn($item) =>
                strtolower($item['courier_service_code']) === strtolower($courierServiceCode)
            );

            if (!$byService) {
                throw new \Exception(
                    "Service {$courierServiceCode} tidak tersedia untuk kurir {$courierCode}"
                );
            }

            if ((int) $byService['price'] !== (int) $shippingPrice) {
                throw new \Exception(
                    "Harga ongkir berubah. Server: {$byService['price']}, Client: {$shippingPrice}"
                );
            }

            $service = $byService;

            // 🔒 VALIDASI HARGA
            if ((int) $service['price'] !== (int) $shippingPrice) {
                throw new \Exception('Harga ongkir berubah, silakan pilih ulang kurir');
            }

            $shippingCost = (int) $service['price'];

            // 6️⃣ Total FINAL
            $total = max(
                0,
                $subtotal - $voucherDiscount + $shippingCost
            );

            // 7️⃣ Create order
            $order = Order::create([
                'user_id' => $user->id,
                'shipping_address_id' => $address->id,
                'order_number' => 'ORD-' . now()->format('YmdHis') . '-' . Str::random(6),

                'subtotal_amount' => $subtotal,
                'voucher_id' => $voucher?->id,
                'voucher_discount' => $voucherDiscount,

                'shipping_cost' => $shippingCost,
                'total_amount' => $total,

                'courier' => $courierCode,
                'courier_service' => strtoupper($courierServiceCode),

                'payment_status' => 'pending',
                'status' => 'pending',
            ]);


            // 8️⃣ Order items + kurangi stok
            foreach ($cart->items as $cartItem) {
                $product = $products[$cartItem->product_id];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $product->price,
                    'subtotal' => $product->price * $cartItem->quantity,
                ]);

                $product->decrement('stock', $cartItem->quantity);
            }

            // 9️⃣ Increment usage voucher (jika dipakai)
            if ($voucher) {
                $voucher->increment('usage_count');
            }


            return $order;
        });
    }


}
