<?php

namespace App\Services;

use App\Enums\StatutRecyclage;
use App\Models\BtSession;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

class RecyclageService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly TransformationCalculService $calculService
    ) {}

    public function validerSession(BtSession $session, Utilisateur $valideur): void
    {
        if ($session->statut !== 'ouverte') {
            throw new \DomainException("La session {$session->session_numero} est déjà validée.");
        }

        DB::transaction(function () use ($session, $valideur) {
            $session->loadMissing(
                'bonTransformation',
                'matieres.matiere',
                'employes.employe.poste',
                'evenements'
            );

            $bt = $session->bonTransformation;

            foreach ($session->matieres->where('type', 'sortie') as $line) {
                $quantiteNette = max(0, (float) $line->quantite - (float) $line->quantite_restituee);

                if ($quantiteNette > 0) {
                    $this->stockService->sortie(
                        locationId: $bt->location_id,
                        entiteType: 'matiere',
                        entiteId: $line->matiere_id,
                        quantite: $quantiteNette,
                        referenceType: 'bt_session',
                        referenceId: $session->id,
                        operateur: $valideur,
                        classementId: null
                    );
                }
            }

            foreach ($session->matieres->where('type', 'entree') as $line) {
                if ((float) $line->quantite <= 0) {
                    continue;
                }

                $this->stockService->entree(
                    locationId: $bt->location_id,
                    entiteType: 'matiere',
                    entiteId: $line->matiere_id,
                    quantite: (float) $line->quantite,
                    referenceType: 'bt_session',
                    referenceId: $session->id,
                    operateur: $valideur,
                    classementId: null
                );
            }

            $calcul = $this->calculService->calculateAndPersistSession($session);

            $session->update([
                'statut' => 'validee',
                'ecarts' => (float) $calcul->taux_perte,
                'valide_by' => $valideur->id,
            ]);

            $this->recalculerStatutBt($session->bonTransformation);
        });
    }

    private function recalculerStatutBt($bt): void
    {
        $bt->refresh();

        $quantitePrevue = (float) $bt->quantite_entree;
        $quantiteConsommee = $bt->quantiteNetteConsommeeTotale();

        if ($quantitePrevue > 0 && $quantiteConsommee >= $quantitePrevue) {
            $bt->update(['statut' => StatutRecyclage::CLOTURE->value]);
            return;
        }

        if ($bt->sessions()->where('statut', 'validee')->exists()) {
            $bt->update(['statut' => StatutRecyclage::EN_COURS->value]);
            return;
        }

        $bt->update(['statut' => StatutRecyclage::OUVERT->value]);
    }
}