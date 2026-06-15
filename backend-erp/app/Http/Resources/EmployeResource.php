<?php
// app/Http/Resources/EmployeResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'matricule'     => $this->matricule,
            'nom'           => $this->nom,
            'prenom'        => $this->prenom,
            'nom_complet'   => $this->nomComplet(),
            'date_embauche' => $this->date_embauche?->toDateString(),
            'date_depart'   => $this->date_depart?->toDateString(),
            'actif'         => $this->actif,
            'anciennete'    => $this->anciennete(),
            'poste'         => $this->whenLoaded('poste', fn() => [
                'id'          => $this->poste->id,
                'nom'         => $this->poste->nom,
                'taux_horaire'=> (float) $this->poste->taux_horaire,
            ]),
            'created_at'    => $this->created_at?->toDateString(),
        ];
    }
}