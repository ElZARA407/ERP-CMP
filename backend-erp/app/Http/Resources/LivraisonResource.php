<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LivraisonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'date_livraison' => $this->date_livraison?->toDateString(),
            'statut' => $this->statut,
            'reference_bc' => $this->reference_bc,
            'reference_facture' => $this->reference_facture,
            'chauffeur' => $this->chauffeur,
            'vehicule' => $this->vehicule,
            'observations' => $this->observations,
            'est_facturee' => $this->estFacturee(),

            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'nom' => $this->client->nom,
            ]),

            'lignes' => $this->whenLoaded('lignes', fn () =>
                $this->lignes->map(fn ($ligne) => [
                    'id' => $ligne->id,
                    'produit_id' => $ligne->produit_id,
                    'classement_id' => $ligne->classement_id,
                    'ligne_commande_id' => $ligne->ligne_commande_id,
                    'ligne_vente_directe_id' => $ligne->ligne_vente_directe_id,
                    'quantite_livree' => (float) $ligne->quantite_livree,

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

            'facture' => $this->whenLoaded('facture', fn () =>
                $this->facture ? [
                    'id' => $this->facture->id,
                    'numero' => $this->facture->numero,
                    'statut' => $this->facture->statut->value,
                ] : null
            ),

            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}