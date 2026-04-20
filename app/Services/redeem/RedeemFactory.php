<?php

namespace App\Services\Redeem;

use Exception;

class RedeemFactory
{
    public static function make(string $type): RewardRedeemInterface
    {
        return match ($type) {
            'voucher' => app(VoucherRedeemService::class),
            'product' => app(ProductRedeemService::class),
            'hotel' => app(HotelRedeemService::class),
            default => throw new Exception('Tipe reward tidak dikenali'),
        };
    }
}