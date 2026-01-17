<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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
}
