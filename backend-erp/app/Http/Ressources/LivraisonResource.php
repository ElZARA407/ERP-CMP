<?php
// app/Http/Resources/LivraisonResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LivraisonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'numero'             => $this->numero,
            'source_type'        => $this->source_type,
            'source_id'          => $this->source_id,
            'date_livraison'     => $this->date_livraison?->toDateString(),
            'statut'             => $this->statut,
            'reference_bc'       => $this->reference_bc,
            'reference_facture'  => $this->reference_facture,
            'chauffeur'          => $this->chauffeur,
            'vehicule'           => $this->vehicule,
            'observations'       => $this->observations,
            'est_facturee'       => $this->estFacturee(),
            'client'             => $this->whenLoaded('client', fn() => [
                'id'  => $this->client->id,
                'nom' => $this->client->nom,
            ]),
            'lignes'             => $this->whenLoaded('lignes', fn() =>
                $this->lignes->map(fn($l) => [
                    'id'               => $l->id,
                    'quantite_livree'  => (float) $l->quantite_livree,
                    'classement'       => [
                        'id'          => $l->classement->id,
                        'designation' => $l->classement->designation(),
                    ],
                ])
            ),
            'facture'            => $this->whenLoaded('facture', fn() =>
                $this->facture ? [
                    'id'     => $this->facture->id,
                    'numero' => $this->facture->numero,
                    'statut' => $this->facture->statut->value,
                ] : null
            ),
            'created_at'         => $this->created_at?->toDateTimeString(),
        ];
    }
}