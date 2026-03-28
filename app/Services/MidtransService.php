<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    public static function init(): void
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public static function verifySignature(array $payload): bool
    {
        if (
            !isset(
            $payload['order_id'],
            $payload['status_code'],
            $payload['gross_amount'],
            $payload['signature_key']
        )
        ) {
            return false;
        }

        $serverKey = config('midtrans.server_key');

        $signature = hash(
            'sha512',
            $payload['order_id']
            . $payload['status_code']
            . $payload['gross_amount']
            . $serverKey
        );

        return hash_equals($signature, $payload['signature_key']);
    }

    public static function mapPaymentStatus(string $transactionStatus, ?string $fraudStatus = null): array
    {
        switch ($transactionStatus) {

            case 'capture':
                return $fraudStatus === 'accept'
                    ? ['payment_status' => 'paid', 'status' => 'processing']
                    : ['payment_status' => 'pending', 'status' => 'pending'];

            case 'settlement':
                return ['payment_status' => 'paid', 'status' => 'processing'];

            case 'pending':
                return ['payment_status' => 'pending', 'status' => 'pending'];

            case 'deny':
            case 'cancel':
            case 'expire':
                return ['payment_status' => 'failed', 'status' => 'cancelled'];

            default:
                return ['payment_status' => 'pending', 'status' => 'pending'];
        }
    }

    public static function createSnapToken(array $params): string
    {
        self::init();
        return Snap::getSnapToken($params);
    }
    public static function refund($orderId, array $params = [])
    {
        self::init();

        $payload = [
            'refund_key' => 'refund-' . uniqid(),
            'amount' => isset($params['amount'])
                ? (int) round($params['amount'])
                : null,
            'reason' => $params['reason'] ?? 'Refund'
        ];

        try {
            return \Midtrans\Transaction::refund($orderId, $payload);
        } catch (\Exception $e) {
            Log::error('Midtrans refund error', [
                'order_id' => $orderId,
                'payload' => $payload,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
