<?php
// app/Http/Resources/BonTransformationResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BonTransformationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'numero'               => $this->numero,
            'date'                 => $this->date?->toDateString(),
            'machine_broyage'      => $this->machine_broyage,
            'quantite_entree'      => (float) $this->quantite_entree,
            'quantite_broyee'      => $this->quantiteBroyeeTotale(),
            'taux_rendement'       => $this->tauxRendementGlobal(),
            'taux_perte'           => $this->tauxPerteGlobal(),
            'statut'               => [
                'valeur'  => $this->statut->value,
                'libelle' => $this->statut->label(),
            ],
            'location'             => $this->whenLoaded('location', fn() => [
                'id'  => $this->location->id,
                'nom' => $this->location->nom,
            ]),
            'matiere_brute'        => $this->whenLoaded('matiereBrute', fn() => [
                'id'        => $this->matiereBrute->id,
                'nom'       => $this->matiereBrute->nom,
                'reference' => $this->matiereBrute->reference,
            ]),
            'matiere_broyee'       => $this->whenLoaded('matiereBroyee', fn() => [
                'id'        => $this->matiereBroyee->id,
                'nom'       => $this->matiereBroyee->nom,
                'reference' => $this->matiereBroyee->reference,
            ]),
            'sessions'             => BtSessionResource::collection(
                $this->whenLoaded('sessions')
            ),
            'created_at'           => $this->created_at?->toDateTimeString(),
        ];
    }
}