<?php
// app/Http/Resources/CategorieProduitResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategorieProduitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'nom'            => $this->nom,
            'produits_count' => $this->whenCounted('produits'),
            'created_at'     => $this->created_at?->toDateString(),
        ];
    }
}