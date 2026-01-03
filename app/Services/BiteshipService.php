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
        $keyword = trim($city . ' ' . $province);

        $res = self::client()->get(
            'https://api.biteship.com/v1/locations',
            [
                'search' => $keyword,
            ]
        )->json();

        if (empty($res['locations'])) {
            return null;
        }

        return collect($res['locations'])
            ->first(
                fn($loc) =>
                strcasecmp(
                    $loc['administrative_division_level_2_name'] ?? '',
                    $city
                ) === 0
            )
            ?? $res['locations'][0];
    }



    // DIGUNAKAN SAAT CHECKOUT
    public static function getRates(array $payload): array
    {
        return self::client()
            ->post('https://api.biteship.com/v1/rates/couriers', $payload)
            ->json();
    }
}
