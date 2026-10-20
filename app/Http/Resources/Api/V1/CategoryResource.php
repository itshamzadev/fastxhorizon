<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'slug' => (string) $this->slug,
            'parent_id' => (int) $this->parent_id,
            'position' => (int) $this->position,
            'priority' => (int) $this->priority,
            'home_status' => (bool) $this->home_status,
            'icon_url' => $this->icon_full_url,
        ];
    }
}
