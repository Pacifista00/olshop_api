<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'order_number' => $this->order_number,
            'payment_status' => $this->payment_status,

            'created_at' => $this->created_at?->toISOString(),
            'created_at_formatted' => $this->created_at?->format('d M Y H:i'),

            'subtotal_amount' => (int) $this->subtotal_amount,
            'shipping_cost' => (int) $this->shipping_cost,
            'voucher_discount' => (int) $this->voucher_discount,
            'total_amount' => (int) $this->total_amount,

            'courier' => [
                'code' => $this->courier,
                'service' => $this->courier_service,
                'etd' => $this->courier_etd,
            ],

            'items' => OrderItemResource::collection(
                $this->whenLoaded('items')
            ),
        ];
    }
}
