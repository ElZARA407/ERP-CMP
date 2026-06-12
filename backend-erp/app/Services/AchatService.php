<?php
// app/Services/AchatService.php

namespace App\Services;

use App\Models\JournalAchat;
use App\Models\LigneAchat;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

/**
 * Service de gestion du cycle P2P (Purchase-to-Pay).
 *
 * Responsabilités :
 *   1. Calcul des totaux lignes et totaux BR
 *   2. Validation du BR → entrée stock + mise à jour PMP
 *   3. Mise à jour prix_moyen (PMP) de chaque matière
 */
class AchatService
{
    public function __construct(
        private readonly StockService $stockService
    ) {}

    // ── Calcul total BR ─────────────────────────────────────
    public function calculerTotal(JournalAchat $br): float
    {
        $total = 0;

        foreach ($br->lignes as $ligne) {
            $totalLigne = round(
                (float) $ligne->quantite * (float) $ligne->prix_unitaire,
                2
            );
            $ligne->update(['total_ligne' => $totalLigne]);
            $total += $totalLigne;
        }

        $br->update(['total' => $total]);

        return $total;
    }

    // ── Validation BR ───────────────────────────────────────
    public function valider(JournalAchat $br, Utilisateur $valideur): void
    {
        if (!$br->estValidable()) {
            throw new \DomainException(
                "Le BR {$br->numero} ne peut pas être validé."
            );
        }

        DB::transaction(function () use ($br, $valideur) {
            $this->calculerTotal($br);

            foreach ($br->lignes as $ligne) {
                $this->traiterLigneAchat($ligne, $br, $valideur);
            }

            $br->update([
                'statut'    => 'valide',
                'valide_by' => $valideur->id,
            ]);
        });
    }

    // ── Traitement d'une ligne ──────────────────────────────
    private function traiterLigneAchat(
        LigneAchat   $ligne,
        JournalAchat $br,
        Utilisateur  $valideur
    ): void {
        $matiere = $ligne->matiere;

        // 1. Mettre à jour le Prix Moyen Pondéré
        $nouveauPMP = $matiere->calculerNouveauPMP(
            (float) $ligne->quantite,
            (float) $ligne->prix_unitaire
        );
        $matiere->update(['prix_moyen' => $nouveauPMP]);

        // 2. Entrée stock
        $this->stockService->entree(
            locationId     : $br->location_id,
            entiteType     : 'matiere',
            entiteId       : $matiere->id,
            quantite       : (float) $ligne->quantite,
            referenceType  : 'journal_achat',
            referenceId    : $br->id,
            operateur      : $valideur,
            classementId   : null
        );
    }
}
