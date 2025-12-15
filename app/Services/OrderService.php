<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public static function checkoutFromCart(string $addressId, $user): Order
    {
        return DB::transaction(function () use ($addressId, $user) {

            // 1️⃣ Ambil cart user
            $cart = Cart::where('user_id', $user->id)
                ->with('items.product')
                ->firstOrFail();

            if ($cart->items->isEmpty()) {
                throw new \Exception('Keranjang kosong');
            }

            // 2️⃣ Validasi alamat
            $address = Address::where('id', $addressId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $total = 0;
            $products = [];

            // 3️⃣ Hitung total + cek stok
            foreach ($cart->items as $cartItem) {
                $product = Product::lockForUpdate()
                    ->findOrFail($cartItem->product_id);

                if ($product->stock < $cartItem->quantity) {
                    throw new \Exception("Stok {$product->name} tidak cukup");
                }

                $products[$product->id] = $product;
                $total += $product->price * $cartItem->quantity;
            }

            // 4️⃣ Create order
            $order = Order::create([
                'user_id' => $user->id,
                'shipping_address_id' => $address->id,
                'order_number' => 'ORD-' . now()->format('YmdHis') . '-' . Str::random(6),
                'total_amount' => $total,
                'shipping_cost' => 50000,
                'payment_status' => 'pending',
                'status' => 'pending'
            ]);

            // 5️⃣ Create order items + kurangi stok
            foreach ($cart->items as $cartItem) {
                $product = $products[$cartItem->product_id];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $product->price
                ]);

                $product->decrement('stock', $cartItem->quantity);
            }

            // 6️⃣ Kosongkan cart
            $cart->items()->delete();

            return $order;
        });
    }

}
