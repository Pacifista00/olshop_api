<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (int) $this->price,
            'stock' => (int) $this->stock,
            'weight' => (int) $this->weight,
            'use_dimension' => $this->use_dimension,

            'dimension' => [
                'length' => $this->length,
                'width' => $this->width,
                'height' => $this->height,
            ],

            'image' => $this->image
                ? $this->image
                : null,

            'is_active' => (bool) $this->is_active,

            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ];
            }),

            'created_at' => $this->created_at,
        ];
    }
}
