<?php

namespace App\Http\Resources;

use App\Models\Central\User;
use App\Storage\StorageResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
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
            'email' => $this->email,
            'profile_picture_url' => $this->profile_picture
                ? app(StorageResolver::class)->forSystem()->url($this->profile_picture)
                : null,
            'created_at' => $this->created_at,
        ];
    }
}
