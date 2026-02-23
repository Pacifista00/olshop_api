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
                    continue; // skip produk rusak
                }

                $items[] = [
                    'name' => $item->product->name,
                    'value' => (int) $item->product->price,
                    'quantity' => $item->quantity,
                    'weight' => max(1, (int) $item->product->weight),
                ];
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

}
