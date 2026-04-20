<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'visibility' => $this->visibility,
            'type' => $this->type,
            'value' => $this->value,
            'max_discount' => $this->max_discount,
            'min_order_amount' => $this->min_order_amount,
            'usage_limit' => $this->usage_limit,
            'usage_count' => $this->usage_count,
            'starts_at' => $this->starts_at,
            'expires_at' => $this->expires_at,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                ];
            }),
        ];
    }
}
