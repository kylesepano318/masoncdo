<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'full_name' => trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name, $this->suffix]))), 'position' => $this->position->only('id', 'name', 'slug', 'rank', 'is_officer'), 'photo_url' => $this->profile_photo, 'member_since' => $this->member_since, 'public_biography' => $this->biography];
    }
}
