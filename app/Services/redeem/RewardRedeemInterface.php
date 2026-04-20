<?php

namespace App\Services\Redeem;

use App\Models\User;
use App\Models\Reward;
use App\Models\RewardRedemption;

interface RewardRedeemInterface
{
    public function handle(
        User $user,
        Reward $reward,
        array $payload = [],
        ?RewardRedemption $redeem = null
    );
}