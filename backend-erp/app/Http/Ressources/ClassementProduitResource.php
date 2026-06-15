<?php
// app/Http/Resources/ClassementProduitResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassementProduitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'qualite'         => $this->qualite->value,
            'qualite_libelle' => $this->qualite->label(),
            'prix_specifique' => $this->prix_specifique
                ? (float) $this->prix_specifique
                : null,
            'actif'           => $this->actif,
            'stock_disponible'=> $this->stockDisponible(),
            'produit'         => $this->whenLoaded('produit', fn() => [
                'id'          => $this->produit->id,
                'nomencla'    => $this->produit->nomencla,
                'designation' => $this->produit->designation,
            ]),
        ];
    }
}