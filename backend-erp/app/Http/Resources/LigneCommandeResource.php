<?php
// app/Http/Resources/LigneCommandeResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LigneCommandeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'classement'        => $this->whenLoaded('classement', fn() => [
                'id'          => $this->classement->id,
                'qualite'     => $this->classement->qualite->value,
                'designation' => $this->classement->designation(),
                'produit'     => [
                    'id'        => $this->classement->produit->id,
                    'nomencla'  => $this->classement->produit->nomencla,
                    'designation'=> $this->classement->produit->designation,
                ],
            ]),
            'quantite'          => (float) $this->quantite,
            'quantite_restante' => (float) $this->quantite_restante,
            'prix_unitaire'     => (float) $this->prix_unitaire,
            'total_ligne'       => $this->totalLigne(),
            'etat'              => $this->etat,
            'est_soldee'        => $this->estSoldee(),
        ];
    }
}