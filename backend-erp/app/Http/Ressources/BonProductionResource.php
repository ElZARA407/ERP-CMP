<?php
// app/Http/Resources/BonProductionResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BonProductionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'numero'              => $this->numero,
            'date'                => $this->date?->toDateString(),
            'machine_production'  => $this->machine_production,
            'quantite_cible'      => (float) $this->quantite_cible,
            'quantite_produite'   => $this->quantiteTotaleProduite(),
            'taux_realisation'    => $this->tauxRealisation(),
            'cout_total'          => (float) $this->cout_total,
            'statut'              => [
                'valeur'  => $this->statut->value,
                'libelle' => $this->statut->label(),
            ],
            'location'            => $this->whenLoaded('location', fn() => [
                'id'  => $this->location->id,
                'nom' => $this->location->nom,
            ]),
            'produit'             => $this->whenLoaded('produit', fn() => [
                'id'          => $this->produit->id,
                'nomencla'    => $this->produit->nomencla,
                'designation' => $this->produit->designation,
            ]),
            'sessions'            => BpSessionResource::collection(
                $this->whenLoaded('sessions')
            ),
            'created_at'          => $this->created_at?->toDateTimeString(),
        ];
    }
}