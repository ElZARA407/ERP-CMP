<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $entiteType = $this->getRawOriginal('entite_type');

        return [
            'id'          => $this->id,
            'entite_type' => $entiteType,
            'entite_id'   => $this->entite_id,
            'stock_total' => (float) $this->stock_total,
            'en_rupture'  => $this->estEnRupture(),

            // Désignation de l'entité (produit ou matière)
            'entite'      => $this->whenLoaded('entite', fn() => [
                'id'          => $this->entite->id,
                'designation' => $this->entite->designation,
                'nomencla' => $this->entite->nomencla,
            ]),

            'location'    => $this->whenLoaded('location', fn() => [
                'id'   => $this->location->id,
                'nom'  => $this->location->nom,
                'type' => $this->location->type,
            ]),

            // Classement uniquement pour les produits
            'classement'  => $entiteType === 'produit'
                ? $this->whenLoaded('classement', fn() => [
                    'id'      => $this->classement->id,
                    'qualite' => $this->classement->qualite->value,
                    'libelle' => $this->classement->label(),
                ])
                : null,

            'updated_at'  => $this->updated_at?->toDateTimeString(),
        ];
    }
}