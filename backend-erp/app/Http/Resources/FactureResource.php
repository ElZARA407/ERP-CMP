<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FactureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $livraisons = collect();

        if ($this->relationLoaded('livraisons')) {
            $livraisons = $this->livraisons;
        }

        if ($livraisons->isEmpty() && $this->livraison) {
            $livraisons = collect([$this->livraison]);
        }

        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'date' => $this->date?->toDateString(),
            'total' => (float) $this->total,
            'montant_paye' => (float) ($this->montant_paye ?? 0),
            'reste_a_payer' => $this->resteAPayer(),
            'peut_recevoir_paiement' => $this->peutRecevoirPaiement(),
            'statut' => [
                'valeur' => $this->statut->value,
                'libelle' => $this->statut->label(),
                'couleur' => $this->statut->couleur(),
            ],
            'echeance_paiement' => $this->echeance_paiement?->toDateString(),
            'date_paiement' => $this->date_paiement?->toDateString(),
            'mode_paiement' => $this->mode_paiement?->label(),
            'en_retard' => $this->estEnRetard(),
            'jours_retard' => $this->joursDeRetard(),
            'notes' => $this->notes,

            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'nom' => $this->client->nom,
            ]),

            'livraison' => $this->formatLivraison(
                $this->livraison,
                $this->livraison?->pivot?->total_livraison ?? null,
                $this->livraison?->pivot?->lignes_count ?? null
            ),

            'livraisons' => $livraisons->map(function ($livraison) {
                return $this->formatLivraison(
                    $livraison,
                    $livraison->pivot->total_livraison ?? null,
                    $livraison->pivot->lignes_count ?? null
                );
            })->values(),

            'livraison_count' => $livraisons->count(),

            'lignes' => LigneFactureResource::collection($this->whenLoaded('lignes')),

            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }

    private function formatLivraison($livraison, ?float $totalLivraison = null, ?int $lignesCount = null): ?array
    {
        if (! $livraison) {
            return null;
        }

        return [
            'id' => $livraison->id,
            'numero' => $livraison->numero,
            'date_livraison' => $livraison->date_livraison?->toDateString(),
            'statut' => $livraison->statut,
            'reference_bc' => $livraison->reference_bc,
            'reference_facture' => $livraison->reference_facture,
            'total_livraison' => $totalLivraison,
            'lignes_count' => $lignesCount,
        ];
    }
}   