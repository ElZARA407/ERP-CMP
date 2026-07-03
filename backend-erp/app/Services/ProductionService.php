<?php

namespace App\Services;

use App\Enums\StatutProduction;
use App\Models\BonProduction;
use App\Models\BpSession;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

class ProductionService
{
    public function __construct(
        private readonly StockService $stockService
    ) {}

    public function validerSession(BpSession $session, Utilisateur $valideur): void
    {
        if ($session->statut !== 'ouverte') {
            throw new \DomainException("La session {$session->session_numero} est deja validee.");
        }

        DB::transaction(function () use ($session, $valideur) {
            $session->loadMissing(
                'bonProduction',
                'matieres.matiere',
                'obtenus.produit',
                'obtenus.classement',
                'employes',
                'evenements'
            );

            foreach ($session->matieres as $bpMp) {
                $quantiteNette = (float) $bpMp->quantite_utilisee - (float) $bpMp->quantite_restituee;

                if ($quantiteNette > 0) {
                    $this->stockService->sortie(
                        locationId: $session->bonProduction->location_id,
                        entiteType: 'matiere',
                        entiteId: $bpMp->matiere_id,
                        quantite: $quantiteNette,
                        referenceType: 'bp_session',
                        referenceId: $session->id,
                        operateur: $valideur,
                        classementId: null
                    );
                }

                if ((float) $bpMp->quantite_restituee > 0) {
                    $this->stockService->retour(
                        locationId: $session->bonProduction->location_id,
                        entiteType: 'matiere',
                        entiteId: $bpMp->matiere_id,
                        quantite: (float) $bpMp->quantite_restituee,
                        referenceType: 'bp_session',
                        referenceId: $session->id,
                        operateur: $valideur,
                        classementId: null
                    );
                }

                $bpMp->update([
                    'cout_matiere' => round($quantiteNette * (float) $bpMp->matiere->prix_moyen, 2),
                ]);
            }

            foreach ($session->obtenus as $bpObtenu) {
                if (!$bpObtenu->produit_id) {
                    throw new \DomainException('Produit manquant sur une ligne obtenue de production.');
                }

                $this->stockService->entree(
                    locationId: $bpObtenu->destination_location_id,
                    entiteType: 'produit',
                    entiteId: $bpObtenu->produit_id,
                    quantite: (float) $bpObtenu->quantite_produite,
                    referenceType: 'bp_session',
                    referenceId: $session->id,
                    operateur: $valideur,
                    classementId: $bpObtenu->classement_id
                );
            }

            $dureesPauses = $this->calculerDureePauses($session);

            foreach ($session->employes as $bpEmploye) {
                $heuresEffectives = max(0, (float) $bpEmploye->heures_brutes - $dureesPauses);
                $cout = round($heuresEffectives * (float) $bpEmploye->taux_horaire, 2);

                $bpEmploye->update([
                    'heures_effectives' => $heuresEffectives,
                    'cout' => $cout,
                ]);
            }

            $coutTotal = $session->coutTotal();

            $session->update([
                'statut' => 'validee',
                'cout_total' => $coutTotal,
                'valide_by' => $valideur->id,
            ]);

            $this->recalculerCoutBP($session->bonProduction);

            $bp = $session->bonProduction;

            if ($bp->statut === StatutProduction::OUVERT) {
                $bp->update(['statut' => StatutProduction::EN_COURS->value]);
            }
        });
    }

    public function cloturerBP(BonProduction $bp, Utilisateur $valideur): void
    {
        if (!$bp->statut->estActif()) {
            throw new \DomainException("Le bon de production {$bp->numero} ne peut pas etre cloture.");
        }

        $bp->update(['statut' => StatutProduction::CLOTURE->value]);
    }

    private function calculerDureePauses(BpSession $session): float
    {
        return (float) $session->evenements()
            ->where('type_evenement', 'pause')
            ->whereNotNull('heure_fin')
            ->selectRaw('SUM(TIME_TO_SEC(TIMEDIFF(heure_fin, heure_debut)) / 3600) as total')
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