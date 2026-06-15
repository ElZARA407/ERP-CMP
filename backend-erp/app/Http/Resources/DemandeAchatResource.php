<?php
// app/Http/Resources/DemandeAchatResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DemandeAchatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'numero'       => $this->numero,
            'date_demande' => $this->date_demande?->toDateString(),
            'statut'       => $this->statut,
            'observations' => $this->observations,
            'demandeur'    => $this->whenLoaded('demandeur', fn() => [
                'id'  => $this->demandeur->id,
                'nom' => $this->demandeur->nom,
            ]),
            'lignes'       => $this->whenLoaded('lignes', fn() =>
                $this->lignes->map(fn($l) => [
                    'id'               => $l->id,
                    'entite_type'      => $l->entite_type,
                    'entite_id'        => $l->entite_id,
                    'quantite'         => (float) $l->quantite,
                    'observation_ligne'=> $l->observation_ligne,
                ])
            ),
            'created_at'   => $this->created_at?->toDateTimeString(),
        ];
    }
}