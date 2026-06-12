<?php
// app/Http/Resources/FactureResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FactureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'numero'             => $this->numero,
            'date'               => $this->date?->toDateString(),
            'total'              => (float) $this->total,
            'statut'             => [
                'valeur'   => $this->statut->value,
                'libelle'  => $this->statut->label(),
                'couleur'  => $this->statut->couleur(),
            ],
            'echeance_paiement'  => $this->echeance_paiement?->toDateString(),
            'date_paiement'      => $this->date_paiement?->toDateString(),
            'mode_paiement'      => $this->mode_paiement?->label(),
            'en_retard'          => $this->estEnRetard(),
            'jours_retard'       => $this->joursDeRetard(),
            'notes'              => $this->notes,
            'client'             => $this->whenLoaded('client', fn() => [
                'id'  => $this->client->id,
                'nom' => $this->client->nom,
            ]),
            'lignes'             => LigneFactureResource::collection(
                $this->whenLoaded('lignes')
            ),
            'created_at'         => $this->created_at?->toDateTimeString(),
        ];
    }
}