<?php
// app/Http/Resources/LocationResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'nom'              => $this->nom,
            'type'             => $this->type,
            'est_usine'        => $this->estUsine(),
            'utilisateurs_count' => $this->whenCounted('utilisateurs'),
            'created_at'       => $this->created_at?->toDateString(),
        ];
    }
}