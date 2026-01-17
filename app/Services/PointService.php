<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\UserPoint;
use App\Models\PointHistory;

class PointService
{
    const POINT_RATE = 100_000; // Rp 100.000 = 1 poin

    public static function earn(User $user, Order $order): void
    {
        if (
            PointHistory::where('order_id', $order->id)
                ->where('type', 'earn')
                ->exists()
        ) {
            return;
        }

        // Hitung dari subtotal (recommended)
        $points = intdiv($order->subtotal_amount, self::POINT_RATE);

        if ($points <= 0) {
            return;
        }

        $userPoint = UserPoint::firstOrCreate(
            ['user_id' => $user->id],
            [
                'total_points' => 0,
                'total_spent' => 0,
            ]
        );

        $userPoint->increment('total_points', $points);

        PointHistory::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => 'earn',
            'points' => $points,
            'description' => "Poin dari order {$order->order_number}",
        ]);
    }
}
