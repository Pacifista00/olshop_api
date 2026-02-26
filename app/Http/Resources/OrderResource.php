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
        $computedStatus = $this->payment_status;

        if (
            $this->payment_status === 'unpaid' &&
            $this->expired_at &&
            now()->greaterThanOrEqualTo($this->expired_at)
        ) {
            $computedStatus = 'expired';
        }
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'payment_status' => $computedStatus,

            'created_at' => $this->created_at?->toISOString(),
            'expired_at' => $this->expired_at?->toISOString(),
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
