<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContratResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'mois' => $this->mois,
            'actif' => $this->actif,
            'total_contractuel' => $this->totalContractuel(),
            'taux_execution' => $this->tauxExecution(),

            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'nom' => $this->client->nom,
                'reference' => $this->client->reference,
            ]),

            'lignes' => $this->whenLoaded('lignes', fn () =>
                $this->lignes->map(fn ($ligne) => [
                    'id' => $ligne->id,
                    'produit_id' => $ligne->produit_id,
                    'classement_id' => $ligne->classement_id,
                    'quantite_contractuelle' => (float) $ligne->quantite_contractuelle,
                    'quantite_livree_ytd' => (float) $ligne->quantite_livree_ytd,
                    'quantite_restante' => $ligne->quantiteRestante(),
                    'est_solde' => $ligne->estSolde(),
                    'frequence' => $ligne->frequence,
                    'statut' => $ligne->statut,
                    'prix_unitaire' => (float) $ligne->prix_unitaire,

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

            'created_at' => $this->created_at?->toDateString(),
        ];
    }
}