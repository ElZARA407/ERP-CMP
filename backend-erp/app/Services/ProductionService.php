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
        private readonly StockService $stockService,
        private readonly ProductionCostService $productionCostService
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
                $quantiteNette = (float) $bpMp->quantite_utilisee;
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

            $calcul = $this->productionCostService->calculateAndPersistSession($session, $valideur);

            $session->update([
                'statut' => 'validee',
                'cout_total' => (float) $calcul->cout_global,
                'valide_by' => $valideur->id,
            ]);

            $this->recalculerCoutBP($session->bonProduction);
            $this->recalculerStatutBP($session->bonProduction);
        });
    }

    public function cloturerBP(BonProduction $bp, Utilisateur $valideur): void
    {
        if (!$bp->statut->estActif()) {
            throw new \DomainException("Le bon de production {$bp->numero} ne peut pas etre cloture.");
        }

        if ($bp->quantiteTotaleProduite() < (float) $bp->quantite_cible) {
            throw new \DomainException("La quantite cible du bon de production {$bp->numero} n'est pas encore atteinte.");
        }

        $bp->update(['statut' => StatutProduction::CLOTURE->value]);
    }

    private function recalculerStatutBP(BonProduction $bp): void
    {
        $quantiteProduite = $bp->quantiteTotaleProduite();

        if ($quantiteProduite >= (float) $bp->quantite_cible && (float) $bp->quantite_cible > 0) {
            $bp->update(['statut' => StatutProduction::CLOTURE->value]);
            return;
        }

        if ($bp->sessions()->where('statut', 'validee')->exists()) {
            $bp->update(['statut' => StatutProduction::EN_COURS->value]);
            return;
        }

        $bp->update(['statut' => StatutProduction::OUVERT->value]);
    }

    private function recalculerCoutBP(BonProduction $bp): void
    {
        $coutTotal = (float) $bp->sessions()
            ->where('statut', 'validee')
            ->sum('cout_total');

        $bp->update(['cout_total' => $coutTotal]);
    }
}
