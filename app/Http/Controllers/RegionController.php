<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class RegionController extends Controller
{
    public function provinces()
    {
        $response = Http::get('https://wilayah.id/api/provinces.json');

        return response()->json($response->json());
    }

    public function cities($provinceId)
    {
        $response = Http::get("https://wilayah.id/api/regencies/{$provinceId}.json");

        return response()->json($response->json());
    }
}