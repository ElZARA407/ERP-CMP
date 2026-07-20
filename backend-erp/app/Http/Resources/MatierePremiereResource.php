<?php
// app/Http/Resources/MatierePremiereResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatierePremiereResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'reference'   => $this->reference,
            'nom'         => $this->nom,
            'type'        => $this->type,
            'description' => $this->description,
            'unite'       => $this->unite,
            'prix_moyen'  => (float) $this->prix_moyen, 
            'seuil'       => (float) $this->seuil,
            'actif'       => $this->actif,
            'stock_total' => $this->whenLoaded('stocks')
                ? (float) $this->stocks->sum('stock_total')
                : $this->stockTotal(),
            'en_rupture' => $this->whenLoaded('stocks')
                ? $this->stocks->sum('stock_total') <= 0
                : $this->estEnRupture(),
            'created_at'  => $this->created_at?->toDateString(),
        ];
    }
}