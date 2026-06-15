<?php
// app/Http/Resources/UtilisateurResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UtilisateurResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'nom'         => $this->nom,
            'email'       => $this->email,
            'actif'       => $this->actif,
            'role'        => $this->whenLoaded('role', fn() => [
                'id'  => $this->role->id,
                'nom' => $this->role->nom,
            ]),
            'location'    => $this->whenLoaded('location', fn() => [
                'id'   => $this->location->id,
                'nom'  => $this->location->nom,
                'type' => $this->location->type,
            ]),
            'created_at'  => $this->created_at?->toDateTimeString(),
        ];
    }
}