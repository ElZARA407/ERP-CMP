<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenteDirecteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $client = $this->relationLoaded('client') ? $this->client : $this->client()->first();
        $location = $this->relationLoaded('location') ? $this->location : $this->location()->first();

        $lignes = $this->relationLoaded('lignes')
            ? $this->lignes
            : $this->lignes()->with('produit', 'classement')->get();

        $livraisons = $this->relationLoaded('livraisons')
            ? $this->livraisons
            : $this->livraisons()->get();

        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'date' => $this->date?->toDateString(),
            'statut' => $this->statut,
            'total' => (float) $this->total,

            'client' => $client ? [
                'id' => $client->id,
                'nom' => $client->nom,
            ] : null,

            'location' => $location ? [
                'id' => $location->id,
                'nom' => $location->nom,
            ] : null,

            'lignes' => $lignes->map(function ($ligne) {
                $produit = $ligne->relationLoaded('produit') ? $ligne->produit : $ligne->produit()->first();
                $classement = $ligne->relationLoaded('classement') ? $ligne->classement : $ligne->classement()->first();

                return [
                    'id' => $ligne->id,
                    'produit_id' => $ligne->produit_id,
                    'classement_id' => $ligne->classement_id,
                    'quantite' => (float) $ligne->quantite,
                    'prix_unitaire' => (float) $ligne->prix_unitaire,
                    'total_ligne' => (float) $ligne->total_ligne,

                    'produit' => $produit ? [
                        'id' => $produit->id,
                        'nomencla' => $produit->nomencla,
                        'designation' => $produit->designation,
                    ] : null,

                    'classement' => $classement ? [
                        'id' => $classement->id,
                        'qualite' => $classement->qualite?->value,
                        'libelle' => $classement->libelle,
                        'designation' => method_exists($classement, 'label')
                            ? $classement->label()
                            : ($classement->libelle ?? null),
                    ] : null,
                ];
            })->values(),

            'livraisons' => $livraisons->map(fn ($livraison) => [
                'id' => $livraison->id,
                'numero' => $livraison->numero,
                'source_type' => $livraison->source_type,
                'source_id' => $livraison->source_id,
                'date_livraison' => $livraison->date_livraison?->toDateString(),
                'statut' => $livraison->statut,
                'est_facturee' => $livraison->estFacturee(),
                'created_at' => $livraison->created_at?->toDateTimeString(),
            ])->values(),

            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}