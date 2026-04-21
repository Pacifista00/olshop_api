<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RewardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,

            'points_required' => $this->points_required,
            'stock' => $this->stock,
            'redeemed_count' => $this->redeemed_count,
            'is_active' => (bool) $this->is_active,

            // ===== Voucher =====
            'voucher' => $this->when($this->type === 'voucher', [
                'voucher_type' => $this->voucher_type,
                'voucher_value' => $this->voucher_value,
                'max_discount' => $this->max_discount,
                'min_order_amount' => $this->min_order_amount,
            ]),

            // ===== Product =====
            'product' => $this->when($this->type === 'product', [
                'product_name' => $this->product_name,
                'product_price' => $this->product_price,
                'need_shipping' => (bool) $this->need_shipping,
            ]),

            // ===== Hotel =====
            'hotel' => $this->when($this->type === 'hotel', [
                'hotel_name' => $this->hotel_name,
                'room_type' => $this->room_type,
                'location' => $this->location,
            ]),

            'created_at' => $this->created_at,
        ];
    }
}
