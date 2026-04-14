<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'min_version' => $this->min_version,
            'latest_version' => $this->latest_version,
            'force_update' => (bool) $this->force_update,
            'message' => $this->update_message,
        ];
    }
}
