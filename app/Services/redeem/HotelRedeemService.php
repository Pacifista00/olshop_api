<?php

namespace App\Services\Redeem;

use App\Models\HotelBooking;
use App\Models\User;
use App\Models\Reward;
use App\Models\RewardRedemption;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Exception;

class HotelRedeemService implements RewardRedeemInterface
{
    public function handle(
        User $user,
        Reward $reward,
        array $payload = [],
        ?RewardRedemption $redeem = null
    ) {
        if (!$redeem) {
            throw new Exception('Redemption tidak valid');
        }

        if (empty($payload['check_in'])) {
            throw new Exception('Tanggal check-in wajib diisi');
        }

        try {
            $checkIn = Carbon::parse($payload['check_in'])->startOfDay();
        } catch (Exception $e) {
            throw new Exception('Format tanggal tidak valid');
        }

        if ($checkIn->lt(Carbon::today())) {
            throw new Exception('Tanggal check-in tidak boleh di masa lalu');
        }

        $checkOut = $checkIn->copy()->addDay();

        // anti double booking
        $exists = HotelBooking::where('user_id', $user->id)
            ->whereDate('check_in', $checkIn)
            ->exists();

        if ($exists) {
            throw new Exception('Kamu sudah punya booking di tanggal ini');
        }

        // generate unique booking code
        do {
            $code = 'HTL-' . strtoupper(Str::random(8));
        } while (HotelBooking::where('booking_code', $code)->exists());

        $booking = HotelBooking::create([
            'id' => Str::uuid(),
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'reward_redemption_id' => $redeem->id,
            'hotel_name' => $reward->hotel_name,
            'room_type' => $reward->room_type,
            'location' => $reward->location,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'booking_code' => null,
            'status' => 'processing',
        ]);

        return $booking;
    }
}