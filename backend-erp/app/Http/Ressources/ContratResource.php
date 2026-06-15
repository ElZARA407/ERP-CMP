<?php
// app/Http/Resources/ContratResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContratResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'numero'           => $this->numero,
            'mois'             => $this->mois,
            'actif'            => $this->actif,
            'total_contractuel'=> $this->totalContractuel(),
            'taux_execution'   => $this->tauxExecution(),
            'client'           => $this->whenLoaded('client', fn() => [
                'id'        => $this->client->id,
                'nom'       => $this->client->nom,
                'reference' => $this->client->reference,
            ]),
            'lignes'           => $this->whenLoaded('lignes', fn() =>
                $this->lignes->map(fn($l) => [
                    'id'                     => $l->id,
                    'quantite_contractuelle' => (float) $l->quantite_contractuelle,
                    'quantite_livree_ytd'    => (float) $l->quantite_livree_ytd,
                    'quantite_restante'      => $l->quantiteRestante(),
                    'est_solde'              => $l->estSolde(),
                    'frequence'              => $l->frequence,
                    'statut'                 => $l->statut,
                    'prix_unitaire'          => (float) $l->prix_unitaire,
                    'classement'             => $l->classement ? [
                        'id'          => $l->classement->id,
                        'designation' => $l->classement->designation(),
                    ] : null,
                ])
            ),
            'created_at'       => $this->created_at?->toDateString(),
        ];
    }
}