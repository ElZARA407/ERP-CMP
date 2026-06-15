<?php
// app/Http/Resources/RoleResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'nom'                 => $this->nom,
            'description'         => $this->description,
            'utilisateurs_count'  => $this->whenCounted('utilisateurs'),
            'created_at'          => $this->created_at?->toDateString(),
        ];
    }
}