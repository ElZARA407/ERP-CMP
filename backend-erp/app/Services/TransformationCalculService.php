<?php

namespace App\Services;

use App\Models\BtSession;
use App\Models\BtSessionCalcul;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TransformationCalculService
{
    public function calculateAndPersistSession(BtSession $session): BtSessionCalcul
    {
        $snapshot = $this->buildSessionSnapshot($session);
        $calcul = $this->saveSnapshot($session, $snapshot);

        $this->syncEmployes($session, $snapshot['details_json']['employes'] ?? []);

        return $calcul;
    }

    public function buildSessionSnapshot(BtSession $session): array
    {
        $session->loadMissing(
            'matieres.matiere',
            'employes.employe.poste',
            'evenements'
        );

        $evenements = $session->evenements instanceof Collection
            ? $session->evenements
            : collect($session->evenements);

        $tempsBrut = round($this->sumDurationsByType($evenements, 'broyage'), 2);
        $tempsPause = round($this->sumDurationsByType($evenements, 'pause'), 2);
        $tempsPanne = round($this->sumDurationsByType($evenements, 'panne'), 2);
        $tempsAutre = round($this->sumDurationsByType($evenements, 'autre'), 2);
        $deductions = round($tempsPause + $tempsPanne + $tempsAutre, 2);
        $tempsEffectif = round(max(0, $tempsBrut - $deductions), 2);

        $sorties = [];
        $entrees = [];

        $quantiteBruteUtilisee = 0.0;
        $quantiteRestituee = 0.0;
        $quantiteBroyeeObtenue = 0.0;

        foreach ($session->matieres as $line) {
            if ($line->type === 'sortie') {
                $qte = round((float) $line->quantite, 3);
                $retour = round((float) $line->quantite_restituee, 3);
                $nette = round(max(0, $qte - $retour), 3);

                $quantiteBruteUtilisee += $qte;
                $quantiteRestituee += $retour;

                $sorties[] = [
                    'matiere_id' => $line->matiere_id,
                    'reference' => $line->matiere?->reference,
                    'nom' => $line->matiere?->nom,
                    'quantite_utilisee' => $qte,
                    'quantite_restituee' => $retour,
                    'quantite_nette' => $nette,
                ];
            }

            if ($line->type === 'entree') {
                $qte = round((float) $line->quantite, 3);
                $quantiteBroyeeObtenue += $qte;

                $entrees[] = [
                    'matiere_id' => $line->matiere_id,
                    'reference' => $line->matiere?->reference,
                    'nom' => $line->matiere?->nom,
                    'quantite_produite' => $qte,
                ];
            }
        }

        $quantiteNette = round(max(0, $quantiteBruteUtilisee - $quantiteRestituee), 3);
        $perte = round(max(0, $quantiteNette - $quantiteBroyeeObtenue), 3);

        $rendement = $quantiteNette > 0
            ? round(($quantiteBroyeeObtenue / $quantiteNette) * 100, 3)
            : 0.0;

        $tauxPerte = $quantiteNette > 0
            ? round(($perte / $quantiteNette) * 100, 3)
            : 0.0;

        $employes = [];

        foreach ($session->employes as $btEmploye) {
            $heuresSaisies = (float) $btEmploye->heures_brutes;

            if ($heuresSaisies > 0) {
                $heuresBrutes = round($heuresSaisies, 2);
                $heuresEffectives = $heuresBrutes;
            } else {
                $heuresBrutes = $tempsBrut;
                $heuresEffectives = $tempsEffectif;
            }

            $tauxHoraire = round((float) ($btEmploye->employe?->tauxHoraireActuel() ?? $btEmploye->taux_horaire), 2);
            $cout = round($heuresEffectives * $tauxHoraire, 2);

            $employes[] = [
                'employe_id' => $btEmploye->employe_id,
                'nom_complet' => $btEmploye->employe?->nomComplet(),
                'matricule' => $btEmploye->employe?->matricule,
                'heures_brutes' => $heuresBrutes,
                'heures_effectives' => $heuresEffectives,
                'taux_horaire' => $tauxHoraire,
                'cout' => $cout,
            ];
        }

        return [
            'quantite_brute_utilisee' => round($quantiteBruteUtilisee, 3),
            'quantite_restituee' => round($quantiteRestituee, 3),
            'quantite_nette_consomme' => $quantiteNette,
            'quantite_broyee_obtenue' => round($quantiteBroyeeObtenue, 3),
            'perte' => $perte,
            'rendement' => $rendement,
            'taux_perte' => $tauxPerte,
            'temps_brut' => $tempsBrut,
            'temps_pause' => $tempsPause,
            'temps_panne' => $tempsPanne,
            'temps_autre' => $tempsAutre,
            'temps_effectif' => $tempsEffectif,
            'details_json' => [
                'sorties' => $sorties,
                'entrees' => $entrees,
                'employes' => $employes,
                'evenements' => $this->buildEventDetails($evenements),
            ],
        ];
    }

    public function saveSnapshot(BtSession $session, array $snapshot): BtSessionCalcul
    {
        return BtSessionCalcul::updateOrCreate(
            ['bt_session_id' => $session->id],
            [
                ...$snapshot,
                'calcule_le' => now(),
            ]
        );
    }

    private function syncEmployes(BtSession $session, array $employes): void
    {
        foreach ($employes as $detail) {
            if (!isset($detail['employe_id'])) {
                continue;
            }

            $line = $session->employes()
                ->where('employe_id', (int) $detail['employe_id'])
                ->first();

            if (!$line) {
                continue;
            }

            $line->update([
                'heures_brutes' => (float) ($detail['heures_brutes'] ?? $line->heures_brutes),
                'heures_effectives' => (float) ($detail['heures_effectives'] ?? $line->heures_effectives),
                'taux_horaire' => (float) ($detail['taux_horaire'] ?? $line->taux_horaire),
                'cout' => (float) ($detail['cout'] ?? $line->cout),
            ]);
        }
    }

    private function sumDurationsByType(Collection $evenements, string $type): float
    {
        return round(
            $evenements
                ->filter(fn ($event) => (string) $event->type_evenement === $type)
                ->sum(fn ($event) => $this->eventDurationHours(
                    (string) $event->heure_debut,
                    $event->heure_fin ? (string) $event->heure_fin : null
                )),
            2
        );
    }

    private function buildEventDetails(Collection $evenements): array
    {
        return $evenements->map(fn ($event) => [
            'type_evenement' => (string) $event->type_evenement,
            'heure_debut' => (string) $event->heure_debut,
            'heure_fin' => $event->heure_fin ? (string) $event->heure_fin : null,
            'description' => $event->description,
            'duree' => $this->eventDurationHours(
                (string) $event->heure_debut,
                $event->heure_fin ? (string) $event->heure_fin : null
            ),
        ])->all();
    }

    private function eventDurationHours(string $heureDebut, ?string $heureFin): float
    {
        if (!$heureFin) {
            return 0.0;
        }

        try {
            $debut = Carbon::createFromFormat('H:i:s', $this->normalizeTime($heureDebut));
            $fin = Carbon::createFromFormat('H:i:s', $this->normalizeTime($heureFin));

            if ($fin->lessThan($debut)) {
                $fin->addDay();
            }

            return round($debut->diffInMinutes($fin) / 60, 2);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? "{$time}:00" : $time;
    }
}