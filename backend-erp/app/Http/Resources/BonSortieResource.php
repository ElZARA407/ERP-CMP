<?php
// app/Http/Resources/BonSortieResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BonSortieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'numero'       => $this->numero,
            'date'         => $this->date?->toDateString(),
            'motif'        => $this->motif,
            'statut'       => $this->statut,
            'observations' => $this->observations,
            'location'     => $this->whenLoaded('location', fn() => [
                'id'  => $this->location->id,
                'nom' => $this->location->nom,
            ]),
            'client'       => $this->whenLoaded('client', fn() =>
                $this->client ? [
                    'id'  => $this->client->id,
                    'nom' => $this->client->nom,
                ] : null
            ),
            'lignes'       => $this->whenLoaded('lignes', fn() =>
                $this->lignes->map(fn($l) => [
                    'id'         => $l->id,
                    'quantite'   => (float) $l->quantite,
                    'classement' => $l->classement ? [
                        'id'          => $l->classement->id,
                        'designation' => $l->classement->designation(),
                    ] : null,
                ])
            ),
            'created_at'   => $this->created_at?->toDateTimeString(),
        ];
    }
}