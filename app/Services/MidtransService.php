<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Product;
use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    public static function init()
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public static function createSnapToken(array $params)
    {
        self::init();
        return Snap::getSnapToken($params);
    }
}
