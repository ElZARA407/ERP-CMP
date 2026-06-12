<?php
// app/Services/FactureService.php

namespace App\Services;

use App\Enums\ModePaiement;
use App\Enums\StatutFacture;
use App\Models\Facture;
use App\Models\Livraison;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

/**
 * Service de gestion de la facturation.
 *
 * Responsabilités :
 *   1. Création facture depuis une livraison
 *   2. Enregistrement d'un paiement
 *   3. Annulation d'une facture
 */
class FactureService
{
    // ── Créer depuis une livraison ──────────────────────────
    public function creerDepuisLivraison(
        Livraison   $livraison,
        Utilisateur $createur
    ): Facture {
        if ($livraison->estFacturee()) {
            throw new \DomainException(
                "La livraison {$livraison->numero} est déjà facturée."
            );
        }

        if ($livraison->statut !== 'livre') {
            throw new \DomainException(
                "Impossible de facturer une livraison non confirmée."
            );
        }

        return DB::transaction(function () use ($livraison, $createur) {
            // Calcul de l'échéance depuis la commande source
            $echeanceJours = $this->getEcheanceJours($livraison);
            $dateEcheance  = now()->addDays($echeanceJours);

            $facture = Facture::create([
                'numero'            => Facture::generateReference('FAC'),
                'livraison_id'      => $livraison->id,
                'client_id'         => $livraison->client_id,
                'date'              => now(),
                'total'             => 0,
                'statut'            => StatutFacture::EMISE->value,
                'echeance_paiement' => $dateEcheance,
                'created_by'        => $createur->id,
            ]);

            // Créer les lignes facture depuis les lignes livraison
            $total = 0;

            foreach ($livraison->lignes as $ligneLivraison) {
                $prixUnitaire = $this->getPrixUnitaire($ligneLivraison);
                $totalLigne   = round(
                    (float) $ligneLivraison->quantite_livree * $prixUnitaire,
                    2
                );

                $facture->lignes()->create([
                    'classement_id'  => $ligneLivraison->classement_id,
                    'quantite'       => $ligneLivraison->quantite_livree,
                    'prix_unitaire'  => $prixUnitaire,
                    'total_ligne'    => $totalLigne,
                ]);

                $total += $totalLigne;
            }

            $facture->update(['total' => $total]);

            // Mettre à jour la référence facture sur le BL
            $livraison->update([
                'reference_facture' => $facture->numero,
            ]);

            return $facture;
        });
    }

    // ── Enregistrer un paiement ─────────────────────────────
    public function enregistrerPaiement(
        Facture     $facture,
        ModePaiement $mode,
        Utilisateur $operateur
    ): void {
        if (!$facture->statut->estPayable()) {
            throw new \DomainException(
                "La facture {$facture->numero} ne peut pas être payée "
                . "(statut : {$facture->statut->label()})."
            );
        }

        $facture->payer($mode);
    }

    // ── Annuler une facture ─────────────────────────────────
    public function annuler(Facture $facture, Utilisateur $operateur): void
    {
        if ($facture->statut === StatutFacture::PAYEE) {
            throw new \DomainException(
                "Impossible d'annuler une facture déjà payée."
            );
        }

        $facture->update(['statut' => StatutFacture::ANNULEE->value]);
    }

    // ── Helpers privés ──────────────────────────────────────
    private function getEcheanceJours(Livraison $livraison): int
    {
        if ($livraison->source_type === 'commande') {
            return (int) ($livraison->source()->first()?->echeance ?? 30);
        }

        return 30; // Défaut pour ventes directes
    }

    private function getPrixUnitaire($ligneLivraison): float
    {
        if ($ligneLivraison->ligne_commande_id) {
            return (float) $ligneLivraison->ligneCommande->prix_unitaire;
        }

        if ($ligneLivraison->ligne_vente_directe_id) {
            return (float) $ligneLivraison->ligneVenteDirecte->prix_unitaire;
        }

        return (float) $ligneLivraison->classement->prix_specifique;
    }
}

