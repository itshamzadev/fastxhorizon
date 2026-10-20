<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->f_name,
            'last_name' => $this->l_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'image' => $this->image,
            'is_active' => (bool) $this->is_active,
            'email_verified' => (bool) $this->email_verified_at,
            'phone_verified' => (bool) $this->is_phone_verified,
        ];
    }
}
