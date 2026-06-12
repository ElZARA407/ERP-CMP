<?php
// app/Services/ProductionService.php

namespace App\Services;

use App\Models\BonProduction;
use App\Models\BpSession;
use App\Models\Utilisateur;
use App\Enums\StatutProduction;
use Illuminate\Support\Facades\DB;

/**
 * Service de gestion du cycle de production.
 *
 * Responsabilités :
 *   1. Validation d'une session → mouvements stock + coûts
 *   2. Clôture d'un bon de production
 *   3. Calcul des coûts session (matières + MO + électricité)
 */
class ProductionService
{
    public function __construct(
        private readonly StockService $stockService
    ) {}

    // ── Validation session ──────────────────────────────────
    public function validerSession(BpSession $session, Utilisateur $valideur): void
    {
        if ($session->statut !== 'ouverte') {
            throw new \DomainException(
                "La session {$session->session_numero} est déjà validée."
            );
        }

        DB::transaction(function () use ($session, $valideur) {
            // 1. Sorties matières premières
            foreach ($session->matieres as $bpMp) {
                $quantiteNette = (float) $bpMp->quantite_utilisee
                               - (float) $bpMp->quantite_restituee;

                if ($quantiteNette > 0) {
                    $this->stockService->sortie(
                        locationId    : $session->bonProduction->location_id,
                        entiteType    : 'matiere',
                        entiteId      : $bpMp->matiere_id,
                        quantite      : $quantiteNette,
                        referenceType : 'bp_session',
                        referenceId   : $session->id,
                        operateur     : $valideur,
                        classementId  : null
                    );
                }

                // Retour matière restituée
                if ((float) $bpMp->quantite_restituee > 0) {
                    $this->stockService->retour(
                        locationId    : $session->bonProduction->location_id,
                        entiteType    : 'matiere',
                        entiteId      : $bpMp->matiere_id,
                        quantite      : (float) $bpMp->quantite_restituee,
                        referenceType : 'bp_session',
                        referenceId   : $session->id,
                        operateur     : $valideur,
                        classementId  : null
                    );
                }

                // Calcul coût matière au PMP actuel
                $bpMp->update([
                    'cout_matiere' => round(
                        $quantiteNette * (float) $bpMp->matiere->prix_moyen,
                        2
                    ),
                ]);
            }

            // 2. Entrées produits obtenus
            foreach ($session->obtenus as $bpObtenu) {
                $this->stockService->entree(
                    locationId    : $bpObtenu->destination_location_id,
                    entiteType    : 'produit',
                    entiteId      : $session->bonProduction->produit_id,
                    quantite      : (float) $bpObtenu->quantite_produite,
                    referenceType : 'bp_session',
                    referenceId   : $session->id,
                    operateur     : $valideur,
                    classementId  : $bpObtenu->classement_id
                );
            }

            // 3. Calcul heures effectives et coûts MO
            $dureesPauses = $this->calculerDureePauses($session);

            foreach ($session->employes as $bpEmploye) {
                $heuresEffectives = max(
                    0,
                    (float) $bpEmploye->heures_brutes - $dureesPauses
                );

                $cout = round(
                    $heuresEffectives * (float) $bpEmploye->taux_horaire,
                    2
                );

                $bpEmploye->update([
                    'heures_effectives' => $heuresEffectives,
                    'cout'              => $cout,
                ]);
            }

            // 4. Calcul coût total session
            $coutTotal = $session->coutTotal();
            $session->update([
                'statut'     => 'validee',
                'cout_total' => $coutTotal,
                'valide_by'  => $valideur->id,
            ]);

            // 5. Mise à jour coût total du BP parent
            $this->recalculerCoutBP($session->bonProduction);

            // 6. Mise à jour statut BP
            $bp = $session->bonProduction;
            if ($bp->statut === StatutProduction::OUVERT) {
                $bp->update(['statut' => StatutProduction::EN_COURS->value]);
            }
        });
    }

    // ── Clôture BP ──────────────────────────────────────────
    public function cloturerBP(BonProduction $bp, Utilisateur $valideur): void
    {
        if (!$bp->statut->estActif()) {
            throw new \DomainException(
                "Le bon de production {$bp->numero} ne peut pas être clôturé."
            );
        }

        $bp->update(['statut' => StatutProduction::CLOTURE->value]);
    }

    // ── Helpers privés ──────────────────────────────────────
    private function calculerDureePauses(BpSession $session): float
    {
        return (float) $session->evenements()
            ->where('type_evenement', 'pause')
            ->whereNotNull('heure_fin')
            ->selectRaw(
                'SUM(TIME_TO_SEC(TIMEDIFF(heure_fin, heure_debut)) / 3600) as total'
            )
            ->value('total');
    }

    private function recalculerCoutBP(BonProduction $bp): void
    {
        $coutTotal = (float) $bp->sessions()
            ->where('statut', 'validee')
            ->sum('cout_total');

        $bp->update(['cout_total' => $coutTotal]);
    }
}
