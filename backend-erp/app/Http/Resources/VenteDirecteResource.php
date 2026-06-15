<?php
// app/Http/Resources/VenteDirecteResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenteDirecteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'numero'     => $this->numero,
            'date'       => $this->date?->toDateString(),
            'statut'     => $this->statut,
            'total'      => (float) $this->total,
            'client'     => $this->whenLoaded('client', fn() => [
                'id'  => $this->client->id,
                'nom' => $this->client->nom,
            ]),
            'location'   => $this->whenLoaded('location', fn() => [
                'id'  => $this->location->id,
                'nom' => $this->location->nom,
            ]),
            'lignes'     => $this->whenLoaded('lignes', fn() =>
                $this->lignes->map(fn($l) => [
                    'id'            => $l->id,
                    'quantite'      => (float) $l->quantite,
                    'prix_unitaire' => (float) $l->prix_unitaire,
                    'total_ligne'   => (float) $l->total_ligne,
                    'classement'    => $l->classement ? [
                        'id'          => $l->classement->id,
                        'designation' => $l->classement->designation(),
                    ] : null,
                ])
            ),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}