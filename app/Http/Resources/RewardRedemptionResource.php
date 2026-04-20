<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RewardRedemptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $details = is_array($this->details) ? $this->details : [];
        return [
            'id' => $this->id,
            'reference_code' => $this->reference_code,
            'points_used' => $this->points_used,
            'status' => $this->status,
            'phone' => $this->phone,

            'created_at' => $this->created_at?->toDateTimeString(),
            'details' => $details
                ? collect($details)
                    ->except([
                        'user_id',
                        'reward_id',
                        'voucher_id',
                        'product_id',
                        'reward_redemption_id',
                        'hotel_id',
                        'id',
                    ])
                    ->toArray()
                : null,
            // 🔥 RELATION: REWARD
            'reward' => [
                'id' => $this->reward?->id,
                'name' => $this->reward?->name,
                'type' => $this->reward?->type,
            ],
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ],

            // 🔥 HOTEL BOOKING
            'hotel_booking' => $this->when(
                $this->reward?->type === 'hotel',
                function () {
                    return [
                        'booking_code' => $this->hotelBooking?->booking_code,
                        'hotel_name' => $this->hotelBooking?->hotel_name,
                        'room_type' => $this->hotelBooking?->room_type,
                        'check_in' => $this->hotelBooking?->check_in,
                        'check_out' => $this->hotelBooking?->check_out,
                        'status' => $this->hotelBooking?->status,
                    ];
                }
            ),
            'redeemed_product' => $this->when(
                $this->reward?->type === 'product',
                function () {
                    return [
                        'product_name' => $this->redeemedProduct?->product_name,
                        'recipient_name' => $this->redeemedProduct?->recipient_name,
                        'address' => $this->redeemedProduct?->address,
                        'phone' => $this->redeemedProduct?->phone,
                        'status' => $this->redeemedProduct?->status,
                        'tracking_number' => $this->redeemedProduct?->tracking_number,
                    ];
                }
            ),

            'voucher' => $this->voucher,

            // 🔥 PRODUCT
            'product' => $this->when(
                $this->reward?->type === 'product',
                fn() => is_array($this->details) ? $this->details : []
            ),
        ];
    }
}
