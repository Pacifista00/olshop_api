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
        Log::info('Biteship courier debug', [
            'courier_db' => $order->courier,
            'service_db' => $order->courier_service,
            'courier_sent' => trim(strtolower($order->courier)),
            'service_sent' => trim(strtolower($order->courier_service)),
        ]);
        $response = Http::timeout(30)->withHeaders([
            'Authorization' => 'Bearer ' . config('services.biteship.key'),
            'Content-Type' => 'application/json',
        ])->post(config('services.biteship.base_url') . '/orders', [

                    'reference_id' => $order->order_number,

                    'shipper_contact_name' => config('services.biteship.shipper_name'),
                    'shipper_contact_phone' => config('services.biteship.shipper_phone'),
                    'shipper_contact_email' => config('services.biteship.shipper_email'),
                    'shipper_organization' => config('services.biteship.shipper_organization'),

                    'origin_contact_name' => config('services.biteship.origin_name'),
                    'origin_contact_phone' => config('services.biteship.origin_phone'),
                    'origin_address' => config('services.biteship.origin_address'),
                    'origin_postal_code' => config('services.biteship.origin_postal_code'),

                    'destination_contact_name' => $order->customer_name,
                    'destination_contact_phone' => $order->customer_phone,
                    'destination_address' => $order->shipping_address_snapshot['street_address'],
                    'destination_postal_code' => $order->shipping_address_snapshot['postal_code'],

                    'courier_company' => strtolower($order->courier),
                    'courier_type' => strtolower($order->courier_service),

                    'delivery_type' => 'now',

                    'items' => $order->items->map(function ($item) {
                        return [
                            'name' => $item->product->name,
                            'description' => $item->product->name,
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
    public function cancelOrder($biteshipOrderId)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.biteship.key'),
            'Content-Type' => 'application/json',
        ])->post("https://api.biteship.com/v1/orders/{$biteshipOrderId}/cancel");

        if (!$response->successful()) {
            throw new \Exception('Biteship cancel failed');
        }

        return $response->json();
    }
}
