<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_code' => $this->booking_code,

            'hotel_name' => $this->hotel_name,
            'room_type' => $this->room_type,
            'location' => $this->location,

            'check_in' => $this->check_in,
            'check_out' => $this->check_out,

            'status' => $this->status,

            'created_at' => $this->created_at?->toDateTimeString(),

            // ✅ USER
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                ];
            }),

            // ✅ REWARD
            'reward' => $this->whenLoaded('reward', function () {
                return [
                    'id' => $this->reward?->id,
                    'name' => $this->reward?->name,
                    'type' => $this->reward?->type,
                ];
            }),

            // ✅ REDEMPTION
            'redemption' => $this->whenLoaded('redemption', function () {
                return [
                    'id' => $this->redemption?->id,
                    'reference_code' => $this->redemption?->reference_code,
                    'points_used' => $this->redemption?->points_used,
                    'status' => $this->redemption?->status,
                ];
            }),
        ];
    }
}