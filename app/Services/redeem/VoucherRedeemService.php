<?php

namespace App\Services\Redeem;

use App\Models\User;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Voucher;
use Illuminate\Support\Str;

class VoucherRedeemService implements RewardRedeemInterface
{
    public function handle(
        User $user,
        Reward $reward,
        array $payload = [],
        ?RewardRedemption $redeem = null
    ) {
        $voucher = Voucher::create([
            'id' => Str::uuid(),
            'code' => strtoupper(Str::random(10)),
            'name' => $reward->name,
            'description' => $reward->description,
            'type' => $reward->voucher_type,
            'value' => $reward->voucher_value,
            'max_discount' => $reward->max_discount,
            'min_order_amount' => $reward->min_order_amount,
            'usage_limit' => 1,
            'usage_count' => 0,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'is_active' => 1,
            'visibility' => 'hidden',
            'user_id' => $user->id,
        ]);

        // 🔥 INI YANG KURANG
        if ($redeem) {
            $redeem->update([
                'voucher_id' => $voucher->id
            ]);
        }

        return $voucher;
    }
}