<?php
// app/Http/Resources/JournalAchatResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalAchatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'numero'       => $this->numero,
            'date'         => $this->date?->toDateString(),
            'vehicule'     => $this->vehicule,
            'statut'       => $this->statut,
            'total'        => (float) $this->total,
            'observations' => $this->observations,
            'fournisseur'  => $this->whenLoaded('fournisseur', fn() => [
                'id'  => $this->fournisseur->id,
                'nom' => $this->fournisseur->nom,
            ]),
            'location'     => $this->whenLoaded('location', fn() => [
                'id'  => $this->location->id,
                'nom' => $this->location->nom,
            ]),
            'lignes'       => $this->whenLoaded('lignes', fn() =>
                $this->lignes->map(fn($l) => [
                    'id'            => $l->id,
                    'matiere'       => [
                        'id'        => $l->matiere->id,
                        'nom'       => $l->matiere->nom,
                        'reference' => $l->matiere->reference,
                        'unite'     => $l->matiere->unite,
                    ],
                    'quantite'      => (float) $l->quantite,
                    'prix_unitaire' => (float) $l->prix_unitaire,
                    'total_ligne'   => (float) $l->total_ligne,
                ])
            ),
            'created_at'   => $this->created_at?->toDateTimeString(),
        ];
    }
}