<?php
// app/Http/Resources/MouvementStockResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MouvementStockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entite_type' => $this->getRawOriginal('entite_type'),
            'entite_id' => $this->entite_id,
            'type' => [
                'valeur' => $this->type->value,
                'libelle' => $this->type->label(),
            ],
            'quantite' => (float) $this->quantite,
            'impact_stock' => (float) $this->impactStock(),
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'motif' => $this->motif,
            'stock_theorique' => $this->stock_theorique !== null ? (float) $this->stock_theorique : null,
            'stock_physique' => $this->stock_physique !== null ? (float) $this->stock_physique : null,
            'ecart' => $this->ecart !== null ? (float) $this->ecart : null,
            'date_mouvement' => $this->date_mouvement?->toDateTimeString(),
            'location' => $this->whenLoaded('location', fn() => [
                'id' => $this->location->id,
                'nom' => $this->location->nom,
            ]),
            'utilisateur' => $this->whenLoaded('utilisateur', fn() => [
                'id' => $this->utilisateur->id,
                'nom' => $this->utilisateur->nom,
            ]),
            'entite' => $this->whenLoaded('entite', fn() => [
                'id' => $this->entite->id,
                'designation' => $this->entite->designation ?? $this->entite->nom ?? null,
                'nomencla' => $this->entite->nomencla ?? $this->entite->reference ?? null,
                'nom' => $this->entite->nom ?? null,
                'reference' => $this->entite->reference ?? null,
            ]),
            'classement' => $this->whenLoaded('classement', fn() => $this->classement ? [
                'id' => $this->classement->id,
                'libelle' => $this->classement->label(),
            ] : null),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}