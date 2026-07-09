<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenteDirecteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'date' => $this->date?->toDateString(),
            'statut' => $this->statut,
            'total' => (float) $this->total,

            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'nom' => $this->client->nom,
            ]),

            'location' => $this->whenLoaded('location', fn () => [
                'id' => $this->location->id,
                'nom' => $this->location->nom,
            ]),

            'lignes' => $this->whenLoaded('lignes', fn () =>
                $this->lignes->map(fn ($ligne) => [
                    'id' => $ligne->id,
                    'produit_id' => $ligne->produit_id,
                    'classement_id' => $ligne->classement_id,
                    'quantite' => (float) $ligne->quantite,
                    'prix_unitaire' => (float) $ligne->prix_unitaire,
                    'total_ligne' => (float) $ligne->total_ligne,

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

            'livraisons' => $this->whenLoaded('livraisons', fn () =>
                $this->livraisons->map(fn ($livraison) => [
                    'id' => $livraison->id,
                    'numero' => $livraison->numero,
                    'source_type' => $livraison->source_type,
                    'source_id' => $livraison->source_id,
                    'date_livraison' => $livraison->date_livraison?->toDateString(),
                    'statut' => $livraison->statut,
                    'est_facturee' => $livraison->estFacturee(),
                    'created_at' => $livraison->created_at?->toDateTimeString(),
                ])
            ),

            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}