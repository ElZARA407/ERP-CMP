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
            'motif_libelle' => method_exists($this->resource, 'motifLibelle')
                ? $this->motifLibelle()
                : $this->motif,
            'motif_detail' => $this->motif_detail,
            'statut' => $this->statut,
            'observations' => $this->observations,

            'location' => $this->whenLoaded('location', fn () => [
                'id' => $this->location->id,
                'nom' => $this->location->nom,
            ]),

            'destination_location' => $this->whenLoaded('destinationLocation', fn () =>
                $this->destinationLocation ? [
                    'id' => $this->destinationLocation->id,
                    'nom' => $this->destinationLocation->nom,
                ] : null
            ),

            'client' => $this->whenLoaded('client', fn () =>
                $this->client ? [
                    'id' => $this->client->id,
                    'nom' => $this->client->nom,
                    'reference' => $this->client->reference,
                ] : null
            ),

            'createur' => $this->whenLoaded('createur', fn () =>
                $this->createur ? [
                    'id' => $this->createur->id,
                    'nom' => $this->createur->nom,
                ] : null
            ),

            'valideur' => $this->whenLoaded('valideur', fn () =>
                $this->valideur ? [
                    'id' => $this->valideur->id,
                    'nom' => $this->valideur->nom,
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
                        'qualite' => is_object($ligne->classement->qualite)
                            ? $ligne->classement->qualite->value
                            : $ligne->classement->qualite,
                        'libelle' => $ligne->classement->libelle,
                        'designation' => method_exists($ligne->classement, 'label')
                            ? $ligne->classement->label()
                            : ($ligne->classement->libelle ?? null),
                    ] : null,
                ])->values()
            ),

            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}