<?php
// app/Services/LivraisonService.php

namespace App\Services;

use App\Models\Livraison;
use App\Models\LigneLivraison;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

/**
 * Service de gestion des livraisons.
 *
 * La confirmation de livraison déclenche :
 *   1. Sortie stock par LigneLivraison
 *   2. Mise à jour quantite_restante sur les lignes commande
 *   3. Mise à jour statut commande (partielle ou livrée)
 *   4. Mise à jour quantite_livree_ytd sur les lignes contrat
 */
class LivraisonService
{
    public function __construct(
        private readonly StockService    $stockService,
        private readonly CommandeService $commandeService
    ) {}

    public function confirmerLivraison(
        Livraison   $livraison,
        Utilisateur $operateur
    ): void {
        if ($livraison->statut !== 'prepare') {
            throw new \DomainException(
                "La livraison {$livraison->numero} ne peut pas être confirmée."
            );
        }

        DB::transaction(function () use ($livraison, $operateur) {
            foreach ($livraison->lignes as $ligne) {
                $this->traiterLigneLivraison($ligne, $livraison, $operateur);
            }

            $livraison->update(['statut' => 'livre']);

            // Mise à jour statut commande source si applicable
            if ($livraison->source_type === 'commande') {
                $commande = $livraison->source();
                if ($commande) {
                    $this->commandeService->mettreAJourStatut(
                        $commande->first()
                    );
                }
            }
        });
    }

    private function traiterLigneLivraison(
        LigneLivraison $ligne,
        Livraison      $livraison,
        Utilisateur    $operateur
    ): void {
        // Sortie stock produit depuis le site source de la livraison
        $sourceLocationId = $livraison->source_type === 'commande'
            ? $livraison->source()->first()?->location_id
            : $livraison->source()->first()?->location_id;

        if ($sourceLocationId) {
            $this->stockService->sortie(
                locationId    : $sourceLocationId,
                entiteType    : 'produit',
                entiteId      : $ligne->classement->produit_id,
                quantite      : (float) $ligne->quantite_livree,
                referenceType : 'livraison',
                referenceId   : $livraison->id,
                operateur     : $operateur,
                classementId  : $ligne->classement_id
            );
        }

        // Décrémenter quantité restante sur la ligne commande
        if ($ligne->ligne_commande_id) {
            $this->commandeService->decrementerQuantiteRestante(
                $ligne->ligneCommande,
                (float) $ligne->quantite_livree
            );

            // Mise à jour quantite_livree_ytd sur contrat si applicable
            $this->mettreAJourContrat(
                $ligne->ligneCommande,
                (float) $ligne->quantite_livree
            );
        }
    }

    private function mettreAJourContrat(
        $ligneCommande,
        float $quantiteLivree
    ): void {
        // Chercher la ligne contrat correspondant au même classement
        // pour le mois en cours
        $moisCourant = now()->format('Y-m');

        $ligneContrat = \App\Models\LigneContrat::whereHas('contrat', function ($q) use (
            $ligneCommande, $moisCourant
        ) {
            $q->where('client_id', $ligneCommande->commande->client_id)
              ->where('mois', $moisCourant)
              ->where('actif', true);
        })
        ->where('classement_id', $ligneCommande->classement_id)
        ->first();

        if ($ligneContrat) {
            $ligneContrat->increment('quantite_livree_ytd', $quantiteLivree);
        }
    }
}
