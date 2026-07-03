<?php

namespace App\Services;

use App\Enums\ModePaiement;
use App\Enums\StatutFacture;
use App\Models\Facture;
use App\Models\Livraison;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

class FactureService
{
    public function creerDepuisLivraison(Livraison $livraison, Utilisateur $createur): Facture
    {
        if ($livraison->estFacturee()) {
            throw new \DomainException("La livraison {$livraison->numero} est deja facturee.");
        }

        if ($livraison->statut !== 'livre') {
            throw new \DomainException('Impossible de facturer une livraison non confirmee.');
        }

        return DB::transaction(function () use ($livraison, $createur) {
            $livraison->loadMissing(
                'lignes.produit',
                'lignes.classement',
                'lignes.ligneCommande',
                'lignes.ligneVenteDirecte'
            );

            $echeanceJours = $this->getEcheanceJours($livraison);
            $dateEcheance = now()->addDays($echeanceJours);

            $facture = Facture::create([
                'numero' => Facture::generateReference('FAC'),
                'livraison_id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'date' => now(),
                'total' => 0,
                'statut' => StatutFacture::EMISE->value,
                'echeance_paiement' => $dateEcheance,
                'created_by' => $createur->id,
            ]);

            $total = 0;

            foreach ($livraison->lignes as $ligneLivraison) {
                if (!$ligneLivraison->produit_id) {
                    throw new \DomainException('Produit manquant sur une ligne de livraison.');
                }

                $prixUnitaire = $this->getPrixUnitaire($ligneLivraison);
                $totalLigne = round((float) $ligneLivraison->quantite_livree * $prixUnitaire, 2);

                $facture->lignes()->create([
                    'produit_id' => $ligneLivraison->produit_id,
                    'classement_id' => $ligneLivraison->classement_id,
                    'quantite' => $ligneLivraison->quantite_livree,
                    'prix_unitaire' => $prixUnitaire,
                    'total_ligne' => $totalLigne,
                ]);

                $total += $totalLigne;
            }

            $facture->update(['total' => $total]);

            $livraison->update([
                'reference_facture' => $facture->numero,
            ]);

            return $facture;
        });
    }

    public function enregistrerPaiement(
        Facture $facture,
        ModePaiement $mode,
        Utilisateur $operateur
    ): void {
        if (!$facture->statut->estPayable()) {
            throw new \DomainException(
                "La facture {$facture->numero} ne peut pas etre payee."
            );
        }

        $facture->payer($mode);
    }

    public function annuler(Facture $facture, Utilisateur $operateur): void
    {
        if ($facture->statut === StatutFacture::PAYEE) {
            throw new \DomainException("Impossible d'annuler une facture deja payee.");
        }

        $facture->update(['statut' => StatutFacture::ANNULEE->value]);
    }

    private function getEcheanceJours(Livraison $livraison): int
    {
        if ($livraison->source_type === 'commande') {
            return (int) ($livraison->source()->first()?->echeance ?? 30);
        }

        return 30;
    }

    private function getPrixUnitaire($ligneLivraison): float
    {
        if ($ligneLivraison->ligne_commande_id) {
            return (float) $ligneLivraison->ligneCommande->prix_unitaire;
        }

        if ($ligneLivraison->ligne_vente_directe_id) {
            return (float) $ligneLivraison->ligneVenteDirecte->prix_unitaire;
        }

        return 0;
    }
}