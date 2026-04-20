<?php

namespace App\Services\Redeem;

use App\Models\RedeemedProduct;
use App\Models\User;
use App\Models\Reward;
use App\Models\RewardRedemption;
use Exception;
use Illuminate\Support\Str;

class ProductRedeemService implements RewardRedeemInterface
{
    public function handle(
        User $user,
        Reward $reward,
        array $payload = [],
        ?RewardRedemption $redeem = null
    ) {
        // VALIDASI INPUT WAJIB
        if (empty($payload['address'])) {
            throw new Exception('Alamat wajib diisi untuk penukaran barang');
        }

        if (empty($payload['recipient_name'])) {
            throw new Exception('Nama penerima wajib diisi');
        }

        // SIMPAN ORDER BARANG
        $order = RedeemedProduct::create([
            'id' => Str::uuid(),
            'user_id' => $user->id,
            'reward_redemption_id' => $redeem?->id,
            'product_name' => $reward->product_name,
            'address' => $payload['address'],
            'recipient_name' => $payload['recipient_name'],
            'phone' => $payload['phone'] ?? null,
            'status' => 'processing', // bisa: waiting_process, shipped, completed
        ]);

        return $order;
    }
}