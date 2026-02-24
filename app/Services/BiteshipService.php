<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BiteshipService
{
    public static function client()
    {
        return Http::withHeaders([
            'Authorization' => config('services.biteship.key'),
            'Accept' => 'application/json',
        ]);
    }

    // DIGUNAKAN SAAT SIMPAN ALAMAT
    public static function findLocation(
        string $city,
        string $province
    ): ?array {

        $keyword = trim($city);

        $response = self::client()->get(
            'https://api.biteship.com/v1/maps/areas',
            [
                'countries' => 'ID',
                'input' => $keyword,
                'type' => 'single',
            ]
        );

        if (!$response->successful()) {
            return null;
        }

        $areas = $response->json('areas');

        if (empty($areas)) {
            return null;
        }

        // 1️⃣ Prioritas: city + province match
        foreach ($areas as $area) {
            if (
                strcasecmp(
                    $area['administrative_division_level_2_name'] ?? '',
                    $city
                ) === 0
                &&
                strcasecmp(
                    $area['administrative_division_level_1_name'] ?? '',
                    $province
                ) === 0
            ) {
                return $area;
            }
        }

        // 2️⃣ Fallback: city match saja
        foreach ($areas as $area) {
            if (
                strcasecmp(
                    $area['administrative_division_level_2_name'] ?? '',
                    $city
                ) === 0
            ) {
                return $area;
            }
        }

        // 3️⃣ Fallback terakhir
        return $areas[0];
    }



    // DIGUNAKAN SAAT CHECKOUT
    public static function getRates(array $payload): array
    {
        return self::client()
            ->post('https://api.biteship.com/v1/rates/couriers', $payload)
            ->json();
    }
    public static function createShipment(Order $order)
    {
        $response = Http::withHeaders([
            'Authorization' => config('services.biteship.key'),
            'Content-Type' => 'application/json',
        ])->post(config('services.biteship.base_url') . '/orders', [

                    'reference_id' => $order->order_number,

                    'courier_code' => $order->courier,
                    'courier_service_code' => $order->courier_service,

                    'origin_contact_name' => 'Nama Toko',
                    'origin_contact_phone' => '08123456789',
                    'origin_address' => 'Alamat gudang',

                    'destination_contact_name' => $order->customer_name,
                    'destination_contact_phone' => $order->customer_phone,
                    'destination_address' => $order->shipping_address_snapshot['street_address'],

                    'items' => $order->items->map(function ($item) {
                        return [
                            'name' => $item->product->name,
                            'value' => $item->unit_price,
                            'quantity' => $item->quantity,
                            'weight' => $item->product->weight ?? 1000,
                        ];
                    })->values()->toArray(),

                ]);

        if (!$response->successful()) {
            Log::error('Biteship error response', [
                'status' => $response->status(),
                'body' => $response->body(),
                'json' => $response->json(),
            ]);

            throw new \Exception('Gagal create shipment Biteship: ' . $response->body());
        }

        return $response->json();
    }
}
