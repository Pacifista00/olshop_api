<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Cart;
use App\Services\BiteshipService;
use Illuminate\Http\Request;

class BiteshipController extends Controller
{
    public function previewShipping(Request $request)
    {
        $user = auth()->user();

        $cart = Cart::where('user_id', $user->id)
            ->with('items.product')
            ->firstOrFail();

        if ($cart->items->isEmpty()) {
            throw new \Exception('Keranjang kosong');
        }

        $address = Address::where('is_default', true)->first();

        if (!$address->biteship_location_id) {
            throw new \Exception('Alamat belum valid');
        }

        $items = [];

        foreach ($cart->items as $item) {
            $product = $item->product;

            $items[] = [
                'name' => $product->name,
                'value' => (int) $product->price,
                'quantity' => $item->quantity,
                'weight' => max(1, (int) $product->weight),
            ];
        }

        $rates = BiteshipService::getRates([
            'origin_area_id' => config('services.biteship.origin'),
            'destination_area_id' => $address->biteship_location_id,
            'couriers' => 'jne,jnt,sicepat',
            'items' => $items,
        ]);

        return response()->json([
            'shipping_options' => $rates['pricing'] ?? [],
        ]);
    }

}
