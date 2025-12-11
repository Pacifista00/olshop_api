<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => $this->product->name ?? null,
            'price' => $this->product->price ?? 0,
            'quantity' => $this->quantity,
            'subtotal' => ($this->product->price ?? 0) * $this->quantity,
        ];
    }
}
