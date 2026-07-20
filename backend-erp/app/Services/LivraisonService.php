<?php

namespace App\Services;

use App\Models\LigneContrat;
use App\Models\LigneCommande;
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

    public function annulerLivraison(Livraison $livraison, Utilisateur $operateur): void
    {
        if ($livraison->statut !== 'livre') {
            throw new \DomainException("La livraison {$livraison->numero} ne peut pas etre annulee.");
        }

        if ($livraison->estFacturee()) {
            throw new \DomainException("La livraison {$livraison->numero} est deja facturee et ne peut pas etre annulee.");
        }

        if ($livraison->source_type !== 'commande') {
            throw new \DomainException('L annulation automatique est disponible uniquement pour les livraisons issues de commandes.');
        }

        DB::transaction(function () use ($livraison, $operateur) {
            $livraison->loadMissing(
                'lignes.produit',
                'lignes.classement',
                'lignes.ligneCommande.commande'
            );

            $source = $livraison->source()->first();

            if (! $source) {
                throw new \DomainException('Commande source introuvable pour cette livraison.');
            }

            if (! isset($source->location_id)) {
                throw new \DomainException('Location source introuvable pour cette livraison.');
            }

            foreach ($livraison->lignes as $ligne) {
                $this->revenirLigneLivraison($ligne, $livraison, $operateur, (int) $source->location_id);
            }

            $livraison->update(['statut' => 'retourne']);

            $this->commandeService->mettreAJourStatut($source);
        });
    }

    private function traiterLigneLivraison(
        LigneLivraison $ligne,
        Livraison $livraison,
        Utilisateur $operateur
    ): void {
        if (! $ligne->produit_id) {
            throw new \DomainException('Produit manquant sur une ligne de livraison.');
        }

        $source = $livraison->source()->first();
        $sourceLocationId = $source?->location_id;

        if (! $sourceLocationId) {
            throw new \DomainException('Location source introuvable pour cette livraison.');
        }

        $this->stockService->sortie(
            locationId: (int) $sourceLocationId,
            entiteType: 'produit',
            entiteId: (int) $ligne->produit_id,
            quantite: (float) $ligne->quantite_livree,
            referenceType: 'livraison',
            referenceId: $livraison->id,
            operateur: $operateur,
            classementId: $ligne->classement_id
        );

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

    private function revenirLigneLivraison(
        LigneLivraison $ligne,
        Livraison $livraison,
        Utilisateur $operateur,
        int $locationId
    ): void {
        if (! $ligne->produit_id) {
            throw new \DomainException('Produit manquant sur une ligne de livraison.');
        }

        $this->stockService->retour(
            locationId: $locationId,
            entiteType: 'produit',
            entiteId: (int) $ligne->produit_id,
            quantite: (float) $ligne->quantite_livree,
            referenceType: 'livraison_annulee',
            referenceId: $livraison->id,
            operateur: $operateur,
            classementId: $ligne->classement_id
        );

        if ($ligne->ligne_commande_id) {
            $this->restaurerQuantiteRestante(
                $ligne->ligneCommande,
                (float) $ligne->quantite_livree
            );

            $this->reverserContrat(
                $ligne->ligneCommande,
                (float) $ligne->quantite_livree
            );
        }
    }

    private function restaurerQuantiteRestante(LigneCommande $ligneCommande, float $quantiteLivree): void
    {
        $nouvelleQuantiteRestante = min(
            (float) $ligneCommande->quantite,
            (float) $ligneCommande->quantite_restante + $quantiteLivree
        );

        $ligneCommande->update([
            'quantite_restante' => $nouvelleQuantiteRestante,
        ]);
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

    private function reverserContrat($ligneCommande, float $quantiteLivree): void
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
            $nouvelleValeur = max(0, (float) $ligneContrat->quantite_livree_ytd - $quantiteLivree);

            $ligneContrat->update([
                'quantite_livree_ytd' => $nouvelleValeur,
            ]);
        }
    }
}