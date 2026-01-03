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
    public static function verifySignature(array $payload): bool
    {
        $serverKey = config('midtrans.server_key');

        $signature = hash(
            'sha512',
            $payload['order_id']
            . $payload['status_code']
            . $payload['gross_amount']
            . $serverKey
        );

        return $signature === ($payload['signature_key'] ?? null);
    }
    public static function mapPaymentStatus(string $transactionStatus, ?string $fraudStatus = null): array
    {
        return match ($transactionStatus) {
            'capture' => $fraudStatus === 'accept'
            ? ['payment_status' => 'paid', 'status' => 'processing']
            : ['payment_status' => 'pending', 'status' => 'pending'],

            'settlement' => ['payment_status' => 'paid', 'status' => 'processing'],

            'pending' => ['payment_status' => 'pending', 'status' => 'pending'],

            'deny', 'expire', 'cancel'
            => ['payment_status' => 'failed', 'status' => 'cancelled'],

            default => ['payment_status' => 'pending', 'status' => 'pending'],
        };
    }

    public static function createSnapToken(array $params)
    {
        self::init();
        return Snap::getSnapToken($params);
    }
}
