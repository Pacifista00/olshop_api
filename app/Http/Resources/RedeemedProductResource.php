<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RedeemedProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'product_name' => $this->product_name,
            'recipient_name' => $this->recipient_name,
            'address' => $this->address,
            'phone' => $this->phone,
            'tracking_number' => $this->tracking_number,
            'status' => $this->status,

            'created_at' => $this->created_at?->toDateTimeString(),

            // ✅ USER
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ],

            // ✅ REDEMPTION (opsional, kalau mau ditampilkan)
            'redemption' => [
                'id' => $this->rewardRedemption?->id,
                'reference_code' => $this->rewardRedemption?->reference_code,
                'points_used' => $this->rewardRedemption?->points_used,
            ],
        ];
    }
}