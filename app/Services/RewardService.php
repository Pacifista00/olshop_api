<?php

namespace App\Services;

use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\UserPoint;
use App\Services\Redeem\RedeemFactory;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Str;

class RewardService
{
    public function redeem($user, $rewardId, array $payload = [])
    {
        return DB::transaction(function () use ($user, $rewardId, $payload) {

            $reward = Reward::lockForUpdate()->findOrFail($rewardId);

            $userPoint = UserPoint::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();


            if (!$reward->is_active) {
                throw new Exception('Reward tidak aktif');
            }

            if (!$userPoint || $userPoint->total_points < $reward->points_required) {
                throw new Exception('Poin tidak cukup');
            }

            if (!is_null($reward->stock) && $reward->stock <= 0) {
                throw new Exception('Stok habis');
            }

            // 1. buat redemption dulu
            $redeem = RewardRedemption::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'reward_id' => $reward->id,
                'points_used' => $reward->points_required,
                'status' => 'pending',
                'phone' => $payload['phone'] ?? null,
                'reference_code' => strtoupper(Str::random(12)),
            ]);

            // 2. handler
            try {
                $handler = RedeemFactory::make($reward->type);
                $result = $handler->handle($user, $reward, $payload, $redeem);
            } catch (Exception $e) {
                $redeem->update(['status' => 'cancelled']);
                throw $e;
            }

            // 3. update redemption
            $status = match ($reward->type) {
                'voucher' => 'completed',
                'product', 'hotel' => 'pending',
                default => 'completed',
            };

            $redeem->update([
                'status' => $status,
                'details' => is_object($result)
                    ? json_encode($result->toArray())
                    : json_encode($result),
            ]);

            // 4. potong poin
            $userPoint->decrement('total_points', $reward->points_required);
            $userPoint->increment('total_spent', $reward->points_required);

            // 5. update reward
            if (!is_null($reward->stock)) {
                $reward->decrement('stock');
            }

            $reward->increment('redeemed_count');

            return $redeem;
        });
    }
}