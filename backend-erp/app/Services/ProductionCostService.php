<?php

namespace App\Services;

use App\Models\BpSession;
use App\Models\BpSessionCalcul;
use App\Models\BonProduction;
use App\Models\Utilisateur;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductionCostService
{
    public function calculateAndPersistSession(BpSession $session, ?Utilisateur $valideur = null): BpSessionCalcul
    {
        $snapshot = $this->buildSessionSnapshot($session);
        $calcul = $this->saveSnapshot($session, $snapshot, $valideur);

        $this->syncMatieres($session, $snapshot['details_json']['matieres'] ?? []);
        $this->syncEmployes($session, $snapshot['details_json']['employes'] ?? []);

        return $calcul;
    }

    public function buildSessionSnapshot(BpSession $session): array
    {
        $session->loadMissing(
            'matieres.matiere',
            'obtenus.produit',
            'obtenus.classement',
            'employes.employe.poste',
            'evenements'
        );

        $evenements = $session->evenements instanceof Collection ? $session->evenements : collect($session->evenements);

        $tempsBrut = round($this->sumDurationsByType($evenements, 'production'), 2);
        $tempsPause = round($this->sumDurationsByType($evenements, 'pause'), 2);
        $tempsPanne = round($this->sumDurationsByType($evenements, 'panne'), 2);
        $tempsAutre = round($this->sumDurationsByType($evenements, 'autre'), 2);
        $deductions = round($tempsPause + $tempsPanne + $tempsAutre, 2);

        $matiereDetails = [];
        $coutMatieresTotal = 0.0;

        foreach ($session->matieres as $bpMp) {
            $quantiteUtilisee = round((float) $bpMp->quantite_utilisee, 3);
            $quantiteRestituee = round((float) $bpMp->quantite_restituee, 3);
            $quantiteNette = round(max(0, $quantiteUtilisee - $quantiteRestituee), 3);
            $prixMoyen = (float) ($bpMp->matiere?->prix_moyen ?? 0);
            $cout = round($quantiteNette * $prixMoyen, 2);

            $coutMatieresTotal += $cout;

            $matiereDetails[] = [
                'matiere_id' => $bpMp->matiere_id,
                'reference' => $bpMp->matiere?->reference,
                'nom' => $bpMp->matiere?->nom,
                'quantite_utilisee' => $quantiteUtilisee,
                'quantite_restituee' => $quantiteRestituee,
                'quantite_nette' => $quantiteNette,
                'prix_moyen' => round($prixMoyen, 2),
                'cout' => $cout,
            ];
        }

        $employeDetails = [];
        $coutMainOeuvreTotal = 0.0;

        foreach ($session->employes as $bpEmploye) {
            $heuresBrutes = $this->resolveHeuresBrutes((float) $bpEmploye->heures_brutes, $tempsBrut);
            $heuresEffectives = round(max(0, $heuresBrutes - $deductions), 2);
            $tauxHoraire = round((float) ($bpEmploye->employe?->poste?->tauxHoraireCalcule() ?? $bpEmploye->taux_horaire), 2);
            $cout = round($heuresEffectives * $tauxHoraire, 2);

            $coutMainOeuvreTotal += $cout;

            $employeDetails[] = [
                'employe_id' => $bpEmploye->employe_id,
                'nom_complet' => $bpEmploye->employe?->nomComplet(),
                'matricule' => $bpEmploye->employe?->matricule,
                'heures_brutes' => $heuresBrutes,
                'heures_effectives' => $heuresEffectives,
                'taux_horaire' => $tauxHoraire,
                'cout' => $cout,
            ];
        }

        $quantiteTotaleProduite = round((float) $session->obtenus->sum('quantite_produite'), 3);
        $coutElectricite = round((float) $session->cout_electricite, 2);
        $coutGlobal = round($coutMatieresTotal + $coutMainOeuvreTotal + $coutElectricite, 2);
        $coutUnitaire = $quantiteTotaleProduite > 0
            ? round($coutGlobal / $quantiteTotaleProduite, 4)
            : 0.0;

        return [
            'temps_brut' => round(array_sum(array_column($employeDetails, 'heures_brutes')), 2),
            'temps_pause' => $tempsPause,
            'temps_panne' => $tempsPanne,
            'temps_autre' => $tempsAutre,
            'temps_effectif' => round(array_sum(array_column($employeDetails, 'heures_effectives')), 2),
            'quantite_totale_produite' => $quantiteTotaleProduite,
            'cout_matieres_total' => round($coutMatieresTotal, 2),
            'cout_main_oeuvre_total' => round($coutMainOeuvreTotal, 2),
            'cout_electricite' => $coutElectricite,
            'cout_global' => $coutGlobal,
            'cout_unitaire' => $coutUnitaire,
            'details_json' => [
                'matieres' => $matiereDetails,
                'employes' => $employeDetails,
                'evenements' => $this->buildEventDetails($evenements),
            ],
        ];
    }

    public function saveSnapshot(BpSession $session, array $snapshot, ?Utilisateur $valideur = null): BpSessionCalcul
    {
        return BpSessionCalcul::updateOrCreate(
            ['bp_session_id' => $session->id],
            [
                'temps_brut' => $snapshot['temps_brut'],
                'temps_pause' => $snapshot['temps_pause'],
                'temps_panne' => $snapshot['temps_panne'],
                'temps_autre' => $snapshot['temps_autre'],
                'temps_effectif' => $snapshot['temps_effectif'],
                'quantite_totale_produite' => $snapshot['quantite_totale_produite'],
                'cout_matieres_total' => $snapshot['cout_matieres_total'],
                'cout_main_oeuvre_total' => $snapshot['cout_main_oeuvre_total'],
                'cout_electricite' => $snapshot['cout_electricite'],
                'cout_global' => $snapshot['cout_global'],
                'cout_unitaire' => $snapshot['cout_unitaire'],
                'details_json' => $snapshot['details_json'],
                'calcule_le' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function calculateWeightedAverageForProduct(
        int $produitId,
        ?string $dateDebut = null,
        ?string $dateFin = null
    ): array {
        $query = BpSessionCalcul::query()
            ->with(['session.obtenus.produit', 'session.bonProduction'])
            ->whereHas('session', function ($q) use ($dateDebut, $dateFin) {
                $q->where('statut', 'validee');

                if ($dateDebut) {
                    $q->whereDate('date_session', '>=', $dateDebut);
                }

                if ($dateFin) {
                    $q->whereDate('date_session', '<=', $dateFin);
                }
            })
            ->whereHas('session.obtenus', function ($q) use ($produitId) {
                $q->where('produit_id', $produitId);
            });

        $rows = $query->get();

        $details = [];
        $quantiteTotale = 0.0;
        $pondere = 0.0;
        $tempsEffectifTotal = 0.0;

        foreach ($rows as $calcul) {
            $session = $calcul->session;

            if (!$session) {
                continue;
            }

            $quantiteSessionProduit = (float) $session->obtenus
                ->where('produit_id', $produitId)
                ->sum('quantite_produite');

            if ($quantiteSessionProduit <= 0) {
                continue;
            }

            $coutUnitaire = (float) $calcul->cout_unitaire;

            $quantiteTotale += $quantiteSessionProduit;
            $pondere += $quantiteSessionProduit * $coutUnitaire;
            $tempsEffectifTotal += (float) $calcul->temps_effectif;
            $prod_session = round($quantiteSessionProduit/$calcul->temps_effectif,2);

            $details[] = [
                'bp_session_id' => $session->id,
                'bp_session_numero' => $session->session_numero,
                'date_session' => $session->date_session?->toDateString(),
                'production_session' =>$prod_session,
                'quantite' => round($quantiteSessionProduit, 3),
                'cout_unitaire' => round($coutUnitaire, 2),
                'cout_pondere' => round($quantiteSessionProduit * $coutUnitaire, 2),
                'temps_effectif' => round((float) $calcul->temps_effectif, 2),
            ];
        }

        return [
            'sessions_count' => count($details),
            'quantite_totale' => round($quantiteTotale, 3),
            'temps_effectif_total' => round($tempsEffectifTotal, 2),

            'production_moyenne_session' => count($details) > 0
                ? round($quantiteTotale / count($details), 2)
                : 0.0,

            'production_moyenne_heure' => $tempsEffectifTotal > 0
                ? round($quantiteTotale / $tempsEffectifTotal, 2)
                : 0.0,

            'cout_moyen_pondere' => $quantiteTotale > 0
                ? round($pondere / $quantiteTotale, 2)
                : 0.0,

            'details' => $details,
        ];
    }
    public function calculateWeightedAverageForBp(BonProduction $bp): array
    {
        $bp->loadMissing('sessions.calcul', 'sessions.obtenus', 'produit');

        $sessions = $bp->sessions()
            ->with('calcul', 'obtenus')
            ->where('statut', 'validee')
            ->orderBy('session_numero')
            ->get() ?? collect();

        $details = [];
        $quantiteTotale = 0.0;
        $pondere = 0.0;
        $tempsEffectifTotal = 0.0;

        foreach ($sessions as $session) {
            $calcul = $session->calcul;

            if (!$calcul) {
                continue;
            }

            $quantiteSession = (float) $session->obtenus->sum('quantite_produite');

            if ($quantiteSession <= 0) {
                continue;
            }

            $coutUnitaire = (float) $calcul->cout_unitaire;

            $quantiteTotale += $quantiteSession;
            $pondere += $quantiteSession * $coutUnitaire;
            $tempsEffectifTotal += (float) $calcul->temps_effectif;
            $prod_session = round($quantiteSession/$calcul->temps_effectif,2);

            $details[] = [
                'bp_session_id' => $session->id,
                'bp_session_numero' => $session->session_numero,
                'date_session' => $session->date_session?->toDateString(),
                'quantite' => round($quantiteSession, 3),
                'production_session' =>$prod_session,
                'cout_unitaire' => round($coutUnitaire, 2),
                'cout_pondere' => round($quantiteSession * $coutUnitaire, 2),
                'temps_effectif' => round((float) $calcul->temps_effectif, 2),
            ];
        }

        return [
            'bon_production' => [
                'id' => $bp->id,
                'numero' => $bp->numero,
            ],

            'sessions_count' => count($details),
            'quantite_totale' => round($quantiteTotale, 3),
            'temps_effectif_total' => round($tempsEffectifTotal, 2),

            'production_moyenne_session' => count($details) > 0
                ? round($quantiteTotale / count($details), 2)
                : 0.0,

            'production_moyenne_heure' => $tempsEffectifTotal > 0
                ? round($quantiteTotale / $tempsEffectifTotal, 2)
                : 0.0,

            'cout_moyen_pondere' => $quantiteTotale > 0
                ? round($pondere / $quantiteTotale, 2)
                : 0.0,

            'details' => $details,
        ];
    }

    private function resolveHeuresBrutes(float $heuresBrutesSaisies, float $heuresParDefaut): float
    {
        if ($heuresBrutesSaisies > 0) {
            return round($heuresBrutesSaisies, 2);
        }

        return round(max(0, $heuresParDefaut), 2);
    }

    private function sumDurationsByType(Collection $evenements, string $type): float
    {
        return round(
            $evenements
                ->filter(fn ($evenement) => (string) $evenement->type_evenement === $type)
                ->sum(fn ($evenement) => $this->eventDurationHours(
                    (string) $evenement->heure_debut,
                    $evenement->heure_fin ? (string) $evenement->heure_fin : null
                )),
            2
        );
    }

    private function buildEventDetails(Collection $evenements): array
    {
        return $evenements->map(function ($evenement) {
            return [
                'type_evenement' => (string) $evenement->type_evenement,
                'heure_debut' => (string) $evenement->heure_debut,
                'heure_fin' => $evenement->heure_fin ? (string) $evenement->heure_fin : null,
                'description' => $evenement->description,
                'duree' => $this->eventDurationHours(
                    (string) $evenement->heure_debut,
                    $evenement->heure_fin ? (string) $evenement->heure_fin : null
                ),
            ];
        })->all();
    }

    private function eventDurationHours(string $heureDebut, ?string $heureFin): float
    {
        if (!$heureFin) {
            return 0.0;
        }

        try {
            $debut = Carbon::createFromFormat('H:i:s', $this->normalizeTime($heureDebut))
                ?: Carbon::createFromFormat('H:i', $this->normalizeTime($heureDebut));
            $fin = Carbon::createFromFormat('H:i:s', $this->normalizeTime($heureFin))
                ?: Carbon::createFromFormat('H:i', $this->normalizeTime($heureFin));

            if (!$debut || !$fin) {
                return 0.0;
            }

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

    private function syncMatieres(BpSession $session, array $matieres): void
    {
        foreach ($matieres as $detail) {
            if (!isset($detail['matiere_id'])) {
                continue;
            }

            $bpMp = $session->matieres()->where('matiere_id', (int) $detail['matiere_id'])->first();
            if (!$bpMp) {
                continue;
            }

            $bpMp->update([
                'quantite_utilisee' => (float) ($detail['quantite_utilisee'] ?? $bpMp->quantite_utilisee),
                'quantite_restituee' => (float) ($detail['quantite_restituee'] ?? $bpMp->quantite_restituee),
                'cout_matiere' => (float) ($detail['cout'] ?? $bpMp->cout_matiere),
            ]);
        }
    }

    private function syncEmployes(BpSession $session, array $employes): void
    {
        foreach ($employes as $detail) {
            if (!isset($detail['employe_id'])) {
                continue;
            }

            $bpEmploye = $session->employes()->where('employe_id', (int) $detail['employe_id'])->first();
            if (!$bpEmploye) {
                continue;
            }

            $bpEmploye->update([
                'heures_brutes' => (float) ($detail['heures_brutes'] ?? $bpEmploye->heures_brutes),
                'heures_effectives' => (float) ($detail['heures_effectives'] ?? $bpEmploye->heures_effectives),
                'taux_horaire' => (float) ($detail['taux_horaire'] ?? $bpEmploye->taux_horaire),
                'cout' => (float) ($detail['cout'] ?? $bpEmploye->cout),
            ]);
        }
    }
}
