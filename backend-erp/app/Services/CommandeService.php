<?php
// app/Services/CommandeService.php

namespace App\Services;

use App\Models\Commande;
use App\Models\LigneCommande;
use App\Enums\StatutCommande;

/**
 * Service de gestion des commandes clients.
 *
 * Responsabilités :
 *   1. Calcul des totaux commande
 *   2. Mise à jour des quantités restantes
 *   3. Mise à jour du statut global commande
 */
class CommandeService
{
    // ── Mise à jour statut après livraison ─────────────────
    public function mettreAJourStatut(Commande $commande): void
    {
        $lignes = $commande->lignes;

        $toutesLivrees = $lignes->every(
            fn(LigneCommande $l) => (float) $l->quantite_restante === 0.0
        );

        $aucuneLivree = $lignes->every(
            fn(LigneCommande $l) => (float) $l->quantite_restante === (float) $l->quantite
        );

        $nouveauStatut = match(true) {
            $toutesLivrees => StatutCommande::LIVREE,
            $aucuneLivree  => StatutCommande::NON_LIVREE,
            default        => StatutCommande::PARTIELLE,
        };

        $commande->update(['statut' => $nouveauStatut->value]);
    }

    // ── Décrémenter quantité restante ──────────────────────
    public function decrementerQuantiteRestante(
        LigneCommande $ligne,
        float         $quantiteLivree
    ): void {
        $nouvelle = max(
            0,
            (float) $ligne->quantite_restante - $quantiteLivree
        );

        $ligne->update(['quantite_restante' => $nouvelle]);
    }
}
