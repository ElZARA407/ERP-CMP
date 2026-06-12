<?php
// app/Http/Resources/CommandeResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommandeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'numero'                => $this->numero,
            'date'                  => $this->date?->toDateString(),
            'date_livraison_prevue' => $this->date_livraison_prevue?->toDateString(),
            'statut'                => [
                'valeur'   => $this->statut->value,
                'libelle'  => $this->statut->label(),
                'couleur'  => $this->statut->couleur(),
            ],
            'echeance'              => $this->echeance,
            'total'                 => $this->totalCommande(),
            'en_retard'             => $this->estEnRetard(),
            'client'                => $this->whenLoaded('client', fn() => [
                'id'        => $this->client->id,
                'nom'       => $this->client->nom,
                'reference' => $this->client->reference,
            ]),
            'location'              => $this->whenLoaded('location', fn() => [
                'id'  => $this->location->id,
                'nom' => $this->location->nom,
            ]),
            'lignes'                => LigneCommandeResource::collection(
                $this->whenLoaded('lignes')
            ),
            'created_at'            => $this->created_at?->toDateTimeString(),
        ];
    }
}