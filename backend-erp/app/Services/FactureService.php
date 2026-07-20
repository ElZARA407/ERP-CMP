<?php

namespace App\Services;

use App\Enums\ModePaiement;
use App\Enums\StatutFacture;
use App\Models\Facture;
use App\Models\Livraison;
use App\Models\Utilisateur;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FactureService
{
    public function creerDepuisLivraison(Livraison $livraison, Utilisateur $createur): Facture
    {
        return $this->creerDepuisLivraisons([$livraison->id], $createur);
    }

    public function previsualiserDepuisLivraisons(array $livraisonIds, array $lignesOverride = []): array
    {
        $livraisons = $this->resoudreLivraisonsPourFacture($livraisonIds);

        return $this->construireApercu($livraisons, $lignesOverride);
    }

    public function creerDepuisLivraisons(array $livraisonIds, Utilisateur $createur, array $lignesOverride = []): Facture
    {
        $livraisons = $this->resoudreLivraisonsPourFacture($livraisonIds);

        return DB::transaction(function () use ($livraisons, $createur, $lignesOverride) {
            $aperçu = $this->construireApercu($livraisons, $lignesOverride);

            $facture = Facture::create([
                'numero' => Facture::generateReference('FAC'),
                'livraison_id' => $livraisons->first()->id,
                'client_id' => $livraisons->first()->client_id,
                'date' => now(),
                'total' => 0,
                'statut' => StatutFacture::EMISE->value,
                'echeance_paiement' => now()->addDays($this->getEcheanceJours($livraisons)),
                'created_by' => $createur->id,
            ]);

            foreach ($aperçu['lignes'] as $ligne) {
                $facture->lignes()->create([
                    'produit_id' => $ligne['produit_id'],
                    'classement_id' => $ligne['classement_id'],
                    'quantite' => $ligne['quantite'],
                    'prix_unitaire' => $ligne['prix_unitaire'],
                    'total_ligne' => $ligne['total_ligne'],
                ]);
            }

            $facture->update([
                'total' => $aperçu['total'],
            ]);

            $pivot = [];
            foreach ($aperçu['livraisons'] as $livraison) {
                $pivot[$livraison['id']] = [
                    'total_livraison' => $livraison['total_livraison'],
                    'lignes_count' => $livraison['lignes_count'],
                ];
            }

            $facture->livraisons()->sync($pivot);

            foreach ($livraisons as $livraison) {
                $livraison->update([
                    'reference_facture' => $facture->numero,
                ]);
            }

            return $facture->load('client', 'livraison', 'livraisons', 'lignes.produit', 'lignes.classement');
        });
    }

    public function enregistrerPaiement(
        Facture $facture,
        ModePaiement $mode,
        float $montantPaye,
        Utilisateur $operateur
    ): void {
        if (! $facture->statut->estPayable()) {
            throw new \DomainException(
                "La facture {$facture->numero} ne peut pas etre payee."
            );
        }

        $montantPaye = round($montantPaye, 2);

        if ($montantPaye <= 0) {
            throw new \DomainException('Le montant payé doit être supérieur à 0.');
        }

        DB::transaction(function () use ($facture, $mode, $montantPaye) {
            $facture->refresh();

            $reste = $facture->resteAPayer();

            if ($montantPaye > $reste) {
                throw new \DomainException(
                    sprintf(
                        'Le montant payé dépasse le reste à payer. Reste: %.2f',
                        $reste
                    )
                );
            }

            $facture->payer($mode, $montantPaye);
        });
    }

    private function resoudreLivraisonsPourFacture(array $livraisonIds): Collection
    {
        $ids = collect($livraisonIds)
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw new \DomainException('Au moins une livraison doit etre selectionnee.');
        }

        $ordre = array_flip($ids->all());

        $livraisons = Livraison::query()
            ->with([
                'client',
                'lignes.produit',
                'lignes.classement',
                'lignes.ligneCommande',
                'lignes.ligneVenteDirecte',
            ])
            ->whereIn('id', $ids->all())
            ->get()
            ->sortBy(fn (Livraison $livraison) => $ordre[$livraison->id] ?? PHP_INT_MAX)
            ->values();

        if ($livraisons->count() !== $ids->count()) {
            throw new \DomainException('Une ou plusieurs livraisons sont introuvables.');
        }

        $clientId = $livraisons->first()->client_id;

        foreach ($livraisons as $livraison) {
            if ($livraison->client_id !== $clientId) {
                throw new \DomainException('Toutes les livraisons doivent appartenir au meme client.');
            }

            if ($livraison->statut !== 'livre') {
                throw new \DomainException("La livraison {$livraison->numero} doit etre confirmee avant facturation.");
            }

            if ($livraison->estFacturee()) {
                throw new \DomainException("La livraison {$livraison->numero} est deja facturee.");
            }
        }

        return $livraisons;
    }

    private function construireApercu(Collection $livraisons, array $lignesOverride = []): array
    {
        $client = $livraisons->first()->client;
        $prixOverrides = $this->normaliserPrixOverrides($lignesOverride);

        $livraisonsApercu = [];
        $lignesApercu = [];
        $total = 0.0;

        foreach ($livraisons as $livraison) {
            $totalLivraison = 0.0;
            $lignesLivraison = [];

            foreach ($livraison->lignes as $ligneLivraison) {
                if (! $ligneLivraison->produit_id) {
                    throw new \DomainException('Produit manquant sur une ligne de livraison.');
                }

                $prixUnitaire = $this->getPrixUnitaire($ligneLivraison, $prixOverrides);
                $quantite = (float) $ligneLivraison->quantite_livree;
                $totalLigne = round($quantite * $prixUnitaire, 2);

                $ligne = [
                    'livraison_id' => $livraison->id,
                    'livraison_numero' => $livraison->numero,
                    'ligne_id' => $ligneLivraison->id,
                    'produit_id' => $ligneLivraison->produit_id,
                    'classement_id' => $ligneLivraison->classement_id,
                    'quantite' => $quantite,
                    'prix_unitaire' => $prixUnitaire,
                    'total_ligne' => $totalLigne,
                    'produit' => $ligneLivraison->produit ? [
                        'id' => $ligneLivraison->produit->id,
                        'nomencla' => $ligneLivraison->produit->nomencla,
                        'designation' => $ligneLivraison->produit->designation,
                    ] : null,
                    'classement' => $ligneLivraison->classement ? [
                        'id' => $ligneLivraison->classement->id,
                        'qualite' => is_object($ligneLivraison->classement->qualite) && property_exists($ligneLivraison->classement->qualite, 'value')
                            ? $ligneLivraison->classement->qualite->value
                            : $ligneLivraison->classement->qualite,
                        'libelle' => $ligneLivraison->classement->libelle,
                        'designation' => method_exists($ligneLivraison->classement, 'label')
                            ? $ligneLivraison->classement->label()
                            : ($ligneLivraison->classement->libelle ?? null),
                    ] : null,
                ];

                $lignesApercu[] = $ligne;
                $lignesLivraison[] = $ligne;
                $totalLivraison += $totalLigne;
            }

            $livraisonsApercu[] = [
                'id' => $livraison->id,
                'numero' => $livraison->numero,
                'date_livraison' => $this->formatDateValue($livraison->date_livraison),
                'statut' => $livraison->statut,
                'reference_bc' => $livraison->reference_bc,
                'reference_facture' => $livraison->reference_facture,
                'total_livraison' => round($totalLivraison, 2),
                'lignes_count' => count($lignesLivraison),
                'lignes' => $lignesLivraison,
            ];

            $total += $totalLivraison;
        }

        return [
            'client' => $client ? [
                'id' => $client->id,
                'nom' => $client->nom,
            ] : null,
            'livraison_count' => $livraisons->count(),
            'ligne_count' => count($lignesApercu),
            'total' => round($total, 2),
            'livraisons' => $livraisonsApercu,
            'lignes' => $lignesApercu,
        ];
    }

    private function normaliserPrixOverrides(array $lignesOverride): array
    {
        $overrides = [];

        foreach ($lignesOverride as $ligne) {
            $ligneId = (int) ($ligne['ligne_id'] ?? 0);
            $prix = str_replace(',', '.', (string) ($ligne['prix_unitaire'] ?? ''));

            if ($ligneId > 0 && is_numeric($prix)) {
                $overrides[$ligneId] = round((float) $prix, 2);
            }
        }

        return $overrides;
    }

    private function getEcheanceJours(Collection $livraisons): int
    {
        $premiereLivraison = $livraisons->first();

        if ($premiereLivraison?->source_type === 'commande') {
            return (int) ($premiereLivraison->source()->first()?->echeance ?? 30);
        }

        return 30;
    }

    private function getPrixUnitaire($ligneLivraison, array $prixOverrides = []): float
    {
        if (array_key_exists($ligneLivraison->id, $prixOverrides)) {
            return (float) $prixOverrides[$ligneLivraison->id];
        }

        if ($ligneLivraison->ligne_commande_id) {
            return (float) ($ligneLivraison->ligneCommande?->prix_unitaire ?? 0);
        }

        if ($ligneLivraison->ligne_vente_directe_id) {
            return (float) ($ligneLivraison->ligneVenteDirecte?->prix_unitaire ?? 0);
        }

        throw new \DomainException('Prix unitaire introuvable sur une ligne de livraison.');
    }

    private function formatDateValue(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $value !== '') {
            return substr($value, 0, 10);
        }

        return null;
    }
}