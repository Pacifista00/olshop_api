<?php

namespace App\Http\Controllers;

use App\Http\Resources\CartItemResource;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->where(fn($q) => $q->where('is_active', 1)),
            ],
            'quantity' => 'required|integer|min:1'
        ]);

        return DB::transaction(function () use ($request, $validated) {

            // 🔒 Lock product row
            $product = Product::where('id', $validated['product_id'])
                ->where('is_active', 1)
                ->lockForUpdate()
                ->firstOrFail();

            // ✅ Ambil / buat cart
            $cart = Cart::firstOrCreate([
                'user_id' => $request->user()->id
            ]);

            // ✅ Ambil item jika sudah ada
            $item = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $validated['product_id'])
                ->lockForUpdate()
                ->first();

            $currentQty = $item?->quantity ?? 0;
            $requestedQty = $validated['quantity'];
            $newQty = $currentQty + $requestedQty;

            // ❌ Jika melebihi stok
            if ($newQty > $product->stock) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Quantity melebihi stok tersedia.',
                    'available_stock' => $product->stock,
                    'current_in_cart' => $currentQty
                ], 422);
            }

            // ✅ Update atau create item
            if ($item) {
                $item->increment('quantity', $requestedQty);
            } else {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $validated['product_id'],
                    'quantity' => $requestedQty
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Product added to cart.',
                'data' => new CartResource($cart->load('items.product'))
            ], 201);
        });
    }

    // ✅ UPDATE quantity
    public function update(Request $request, CartItem $cartItem)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        return DB::transaction(function () use ($request, $validated, $cartItem) {

            // 🔒 Ambil + filter ownership SEKALIGUS
            $cartItem = CartItem::where('id', $cartItem->id)
                ->whereHas('cart', fn($q) => $q->where('user_id', $request->user()->id))
                ->lockForUpdate()
                ->first();

            if (!$cartItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to cart item.'
                ], 403);
            }

            // 🔒 Lock product
            $product = Product::where('id', $cartItem->product_id)
                ->where('is_active', 1)
                ->lockForUpdate()
                ->firstOrFail();

            $requestedQty = $validated['quantity'];

            if ($requestedQty > $product->stock) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Quantity melebihi stok tersedia.',
                    'available_stock' => $product->stock
                ], 422);
            }

            $cartItem->update([
                'quantity' => $requestedQty
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Cart item updated successfully.',
                'data' => new CartItemResource($cartItem->load('product'))
            ], 200);
        });
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
