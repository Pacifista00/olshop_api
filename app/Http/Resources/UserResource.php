<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'phone' => $this->phone,
            'gender' => $this->gender,
            'email' => $this->email,
            'role' => $this->role,
            'point' => $this->point,
            'photo' => $this->photo
                ? asset('storage/' . $this->photo)
                : null,
        ];
    }
}
