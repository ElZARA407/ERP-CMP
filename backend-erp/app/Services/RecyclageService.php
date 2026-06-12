<?php
// app/Services/RecyclageService.php

namespace App\Services;

use App\Models\BonTransformation;
use App\Models\BtSession;
use App\Models\Utilisateur;
use App\Enums\StatutRecyclage;
use Illuminate\Support\Facades\DB;

/**
 * Service de gestion du cycle de recyclage/broyage.
 * Architecture miroir de ProductionService.
 */
class RecyclageService
{
    public function __construct(
        private readonly StockService $stockService
    ) {}

    public function validerSession(BtSession $session, Utilisateur $valideur): void
    {
        if ($session->statut !== 'ouverte') {
            throw new \DomainException(
                "La session {$session->session_numero} est déjà validée."
            );
        }

        DB::transaction(function () use ($session, $valideur) {
            $bt = $session->bonTransformation;

            // 1. Entrées et sorties matières
            foreach ($session->matieres as $btMp) {
                if ($btMp->type === 'entree') {
                    $quantiteNette = (float) $btMp->quantite
                                   - (float) $btMp->quantite_restituee;

                    if ($quantiteNette > 0) {
                        $this->stockService->sortie(
                            locationId    : $bt->location_id,
                            entiteType    : 'matiere',
                            entiteId      : $btMp->matiere_id,
                            quantite      : $quantiteNette,
                            referenceType : 'bt_session',
                            referenceId   : $session->id,
                            operateur     : $valideur
                        );
                    }
                } else {
                    // Sortie = matière broyée produite → entrée stock
                    $this->stockService->entree(
                        locationId    : $bt->location_id,
                        entiteType    : 'matiere',
                        entiteId      : $btMp->matiere_id,
                        quantite      : (float) $btMp->quantite,
                        referenceType : 'bt_session',
                        referenceId   : $session->id,
                        operateur     : $valideur
                    );
                }
            }

            // 2. Calcul écarts (taux de perte)
            $ecarts = $session->calculerEcarts();

            // 3. Calcul heures effectives MO
            $dureesPauses = $this->calculerDureePauses($session);

            foreach ($session->employes as $btEmploye) {
                $heuresEffectives = max(
                    0,
                    (float) $btEmploye->heures_brutes - $dureesPauses
                );

                $btEmploye->update([
                    'heures_effectives' => $heuresEffectives,
                    'cout' => round(
                        $heuresEffectives * (float) $btEmploye->taux_horaire,
                        2
                    ),
                ]);
            }

            // 4. Validation session
            $session->update([
                'statut'    => 'validee',
                'ecarts'    => $ecarts,
                'valide_by' => $valideur->id,
            ]);

            // 5. Mise à jour statut BT
            if ($bt->statut === StatutRecyclage::OUVERT) {
                $bt->update(['statut' => StatutRecyclage::EN_COURS->value]);
            }
        });
    }

    private function calculerDureePauses(BtSession $session): float
    {
        return (float) $session->evenements()
            ->where('type_evenement', 'pause')
            ->whereNotNull('heure_fin')
            ->selectRaw(
                'SUM(TIME_TO_SEC(TIMEDIFF(heure_fin, heure_debut)) / 3600) as total'
            )
            ->value('total');
    }
}
