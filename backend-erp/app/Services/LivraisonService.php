<?php

namespace App\Services;

use App\Models\LigneContrat;
use App\Models\LigneLivraison;
use App\Models\Livraison;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

class LivraisonService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly CommandeService $commandeService
    ) {}

    public function confirmerLivraison(Livraison $livraison, Utilisateur $operateur): void
    {
        if ($livraison->statut !== 'prepare') {
            throw new \DomainException("La livraison {$livraison->numero} ne peut pas etre confirmee.");
        }

        DB::transaction(function () use ($livraison, $operateur) {
            $livraison->loadMissing(
                'lignes.produit',
                'lignes.classement',
                'lignes.ligneCommande.commande',
                'lignes.ligneVenteDirecte'
            );

            foreach ($livraison->lignes as $ligne) {
                $this->traiterLigneLivraison($ligne, $livraison, $operateur);
            }

            $livraison->update(['statut' => 'livre']);

            if ($livraison->source_type === 'commande') {
                $commande = $livraison->source()->first();

                if ($commande) {
                    $this->commandeService->mettreAJourStatut($commande);
                }
            }
        });
    }

    private function traiterLigneLivraison(
        LigneLivraison $ligne,
        Livraison $livraison,
        Utilisateur $operateur
    ): void {
        if (!$ligne->produit_id) {
            throw new \DomainException('Produit manquant sur une ligne de livraison.');
        }

        $source = $livraison->source()->first();
        $sourceLocationId = $source?->location_id;

        if ($sourceLocationId) {
            $this->stockService->sortie(
                locationId: $sourceLocationId,
                entiteType: 'produit',
                entiteId: $ligne->produit_id,
                quantite: (float) $ligne->quantite_livree,
                referenceType: 'livraison',
                referenceId: $livraison->id,
                operateur: $operateur,
                classementId: $ligne->classement_id
            );
        }

        if ($ligne->ligne_commande_id) {
            $this->commandeService->decrementerQuantiteRestante(
                $ligne->ligneCommande,
                (float) $ligne->quantite_livree
            );

            $this->mettreAJourContrat(
                $ligne->ligneCommande,
                (float) $ligne->quantite_livree
            );
        }
    }

    private function mettreAJourContrat($ligneCommande, float $quantiteLivree): void
    {
        $moisCourant = now()->format('Y-m');

        $ligneContrat = LigneContrat::whereHas('contrat', function ($query) use ($ligneCommande, $moisCourant) {
            $query->where('client_id', $ligneCommande->commande->client_id)
                ->where('mois', $moisCourant)
                ->where('actif', true);
        })
            ->where('produit_id', $ligneCommande->produit_id)
            ->where('classement_id', $ligneCommande->classement_id)
            ->first();

        if ($ligneContrat) {
            $ligneContrat->increment('quantite_livree_ytd', $quantiteLivree);
        }
    }
}