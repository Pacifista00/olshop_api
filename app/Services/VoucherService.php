<?php

namespace App\Services;

use App\Models\Voucher;
use Carbon\Carbon;

class VoucherService
{
    public static function getActiveVoucher(?string $code): ?Voucher
    {
        if (!$code) {
            return null;
        }

        return Voucher::where('code', $code)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            ->first();
    }

    /**
     * Hitung nilai diskon FINAL
     */
    public static function calculateDiscount(Voucher $voucher, float $subtotal): float
    {
        // minimal order
        if ($voucher->min_order_amount && $subtotal < $voucher->min_order_amount) {
            return 0;
        }

        // percentage
        if ($voucher->type === 'percentage') {
            $discount = ($voucher->value / 100) * $subtotal;

            return $voucher->max_discount
                ? min($discount, $voucher->max_discount)
                : $discount;
        }

        // fixed
        return min($voucher->value, $subtotal);
    }


}
