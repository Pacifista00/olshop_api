<?php

namespace App\Http\Controllers;

use App\Http\Resources\CartItemResource;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    // ✅ GET cart user
    public function index(Request $request)
    {
        $cart = Cart::with('items.product')
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json([
            'status' => 'success',
            'message' => 'Cart retrieved successfully.',
            'data' => $cart ? new CartResource($cart) : null
        ], 200);
    }

    // ✅ ADD product to cart
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ]);

        DB::beginTransaction();
        try {
            // ✅ Ambil / Buat cart
            $cart = Cart::firstOrCreate([
                'user_id' => $request->user()->id
            ]);

            // ✅ Jika produk sudah ada → update qty
            $item = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $validated['product_id'])
                ->first();

            if ($item) {
                $item->update([
                    'quantity' => $item->quantity + $validated['quantity']
                ]);
            } else {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $validated['product_id'],
                    'quantity' => $validated['quantity']
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Product added to cart.',
                'data' => new CartResource($cart->load('items.product'))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add product to cart.',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    // ✅ UPDATE quantity
    public function update(Request $request, CartItem $cartItem)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        DB::beginTransaction();
        try {
            $cartItem->update([
                'quantity' => $validated['quantity']
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Cart item updated successfully.',
                'data' => new CartItemResource($cartItem)
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update cart item.'
            ], 500);
        }
    }

    // ✅ DELETE 1 item
    public function destroy(CartItem $cartItem)
    {
        DB::beginTransaction();
        try {
            $cartItem->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Item removed from cart.',
                'data' => null
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove item.'
            ], 500);
        }
    }

    // ✅ CLEAR CART
    public function clear(Request $request)
    {
        DB::beginTransaction();
        try {
            $cart = Cart::where('user_id', $request->user()->id)->first();

            if ($cart) {
                $cart->items()->delete();
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Cart cleared successfully.',
                'data' => null
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clear cart.'
            ], 500);
        }
    }
}
