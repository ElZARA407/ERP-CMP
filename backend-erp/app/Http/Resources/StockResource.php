<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $entiteType = $this->getRawOriginal('entite_type');
        $stockTotal = (float) $this->stock_total;
        $seuil = $this->whenLoaded('entite', fn () => (float) ($this->entite->seuil ?? 0), null);

        return [
            'id' => $this->id,
            'entite_type' => $entiteType,
            'entite_id' => $this->entite_id,
            'stock_total' => $stockTotal,
            'en_rupture' => $this->estEnRupture(),
            'seuil' => $seuil,
            'sous_seuil_alerte' => $seuil !== null ? $stockTotal <= $seuil : false,
            'entite' => $this->whenLoaded('entite', fn() => [
                'id' => $this->entite->id,
                'designation' => $this->entite->designation ?? $this->entite->nom ?? null,
                'nomencla' => $this->entite->nomencla ?? $this->entite->reference ?? null,
                'nom' => $this->entite->nom ?? null,
                'reference' => $this->entite->reference ?? null,
                'seuil' => $this->entite->seuil ?? null,
            ]),
            'location' => $this->whenLoaded('location', fn() => [
                'id' => $this->location->id,
                'nom' => $this->location->nom,
                'type' => $this->location->type,
            ]),
            'classement' => $entiteType === 'produit'
                ? $this->whenLoaded('classement', fn() => [
                    'id' => $this->classement->id,
                    'qualite' => $this->classement->qualite->value,
                    'libelle' => $this->classement->label(),
                ])
                : null,
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}