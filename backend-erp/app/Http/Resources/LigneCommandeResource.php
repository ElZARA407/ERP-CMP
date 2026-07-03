<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LigneCommandeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'produit_id' => $this->produit_id,
            'classement_id' => $this->classement_id,

            'produit' => $this->whenLoaded('produit', fn () => [
                'id' => $this->produit->id,
                'nomencla' => $this->produit->nomencla,
                'designation' => $this->produit->designation,
            ]),

            'classement' => $this->whenLoaded('classement', fn () => [
                'id' => $this->classement->id,
                'qualite' => $this->classement->qualite?->value,
                'qualite_libelle' => method_exists($this->classement, 'label')
                    ? $this->classement->label()
                    : ($this->classement->libelle ?? null),
                'libelle' => $this->classement->libelle,
            ]),

            'quantite' => (float) $this->quantite,
            'quantite_restante' => (float) $this->quantite_restante,
            'prix_unitaire' => (float) $this->prix_unitaire,
            'total_ligne' => $this->totalLigne(),
            'etat' => $this->etat,
            'est_soldee' => $this->estSoldee(),
        ];
    }
}