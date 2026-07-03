<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BonSortieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'date' => $this->date?->toDateString(),
            'motif' => $this->motif,
            'statut' => $this->statut,
            'observations' => $this->observations,

            'location' => $this->whenLoaded('location', fn () => [
                'id' => $this->location->id,
                'nom' => $this->location->nom,
            ]),

            'client' => $this->whenLoaded('client', fn () =>
                $this->client ? [
                    'id' => $this->client->id,
                    'nom' => $this->client->nom,
                ] : null
            ),

            'lignes' => $this->whenLoaded('lignes', fn () =>
                $this->lignes->map(fn ($ligne) => [
                    'id' => $ligne->id,
                    'produit_id' => $ligne->produit_id,
                    'classement_id' => $ligne->classement_id,
                    'quantite' => (float) $ligne->quantite,

                    'produit' => $ligne->produit ? [
                        'id' => $ligne->produit->id,
                        'nomencla' => $ligne->produit->nomencla,
                        'designation' => $ligne->produit->designation,
                    ] : null,

                    'classement' => $ligne->classement ? [
                        'id' => $ligne->classement->id,
                        'qualite' => $ligne->classement->qualite?->value,
                        'libelle' => $ligne->classement->libelle,
                        'designation' => method_exists($ligne->classement, 'label')
                            ? $ligne->classement->label()
                            : ($ligne->classement->libelle ?? null),
                    ] : null,
                ])
            ),

            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}