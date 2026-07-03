<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LigneFactureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'produit_id' => $this->produit_id,
            'classement_id' => $this->classement_id,
            'quantite' => (float) $this->quantite,
            'prix_unitaire' => (float) $this->prix_unitaire,
            'total_ligne' => (float) $this->total_ligne,

            'produit' => $this->whenLoaded('produit', fn () => [
                'id' => $this->produit->id,
                'nomencla' => $this->produit->nomencla,
                'designation' => $this->produit->designation,
            ]),

            'classement' => $this->whenLoaded('classement', fn () => [
                'id' => $this->classement->id,
                'qualite' => $this->classement->qualite?->value,
                'libelle' => $this->classement->libelle,
                'designation' => method_exists($this->classement, 'label')
                    ? $this->classement->label()
                    : ($this->classement->libelle ?? null),
            ]),
        ];
    }
}