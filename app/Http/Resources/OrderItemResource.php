<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
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
            'quantity' => (int) $this->quantity,
            'unit_price' => (int) $this->unit_price,
            'total_price' => (int) ($this->quantity * $this->unit_price),

            'product' => [
                'name' => $this->product?->name,
                'image_url' => $this->product?->image
                    ? asset('storage/' . $this->product->image)
                    : null,
            ],
        ];
    }
}
