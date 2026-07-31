<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BtSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $calcul = $this->whenLoaded('calcul');

        return [
            'id' => $this->id,
            'session_numero' => $this->session_numero,
            'date_session' => $this->date_session?->toDateString(),
            'machine_id' => $this->machine_id,
            'machine_broyage' => $this->machine_broyage,
            'machine' => $this->whenLoaded('machine', fn () => [
                'id' => $this->machine->id,
                'nom' => $this->machine->nom,
            ]),
            'ecarts' => (float) $this->ecarts,
            'statut' => $this->statut,

            'quantite_sortie' => $this->calcul
                ? (float) $this->calcul->quantite_brute_utilisee
                : $this->quantiteSortie(),
            'quantite_restituee' => $this->calcul
                ? (float) $this->calcul->quantite_restituee
                : $this->quantiteRestituee(),
            'quantite_nette_consomme' => $this->calcul
                ? (float) $this->calcul->quantite_nette_consomme
                : $this->quantiteNetteConsommee(),
            'quantite_entree' => $this->calcul
                ? (float) $this->calcul->quantite_broyee_obtenue
                : $this->quantiteEntree(),

            'matieres' => $this->whenLoaded('matieres', fn () =>
                $this->matieres->map(fn ($m) => [
                    'id' => $m->id,
                    'type' => $m->type,
                    'quantite' => (float) $m->quantite,
                    'quantite_restituee' => (float) $m->quantite_restituee,
                    'matiere' => [
                        'id' => $m->matiere->id,
                        'nom' => $m->matiere->nom,
                        'reference' => $m->matiere->reference,
                        'type' => $m->matiere->type,
                    ],
                ])
            ),

            'employes' => $this->whenLoaded('employes', fn () =>
                $this->employes->map(fn ($e) => [
                    'id' => $e->id,
                    'heures_brutes' => (float) $e->heures_brutes,
                    'heures_effectives' => (float) $e->heures_effectives,
                    'taux_horaire' => (float) $e->taux_horaire,
                    'cout' => (float) $e->cout,
                    'employe' => [
                        'id' => $e->employe->id,
                        'nom_complet' => $e->employe->nomComplet(),
                        'matricule' => $e->employe->matricule,
                        'poste' => $e->employe->poste ? [
                            'id' => $e->employe->poste->id,
                            'nom' => $e->employe->poste->nom,
                        ] : null,
                    ],
                ])
            ),

            'evenements' => $this->whenLoaded('evenements'),
            'calcul' => $this->whenLoaded('calcul', fn () => [
                'id' => $this->calcul->id,
                'quantite_brute_utilisee' => (float) $this->calcul->quantite_brute_utilisee,
                'quantite_restituee' => (float) $this->calcul->quantite_restituee,
                'quantite_nette_consomme' => (float) $this->calcul->quantite_nette_consomme,
                'quantite_broyee_obtenue' => (float) $this->calcul->quantite_broyee_obtenue,
                'perte' => (float) $this->calcul->perte,
                'rendement' => (float) $this->calcul->rendement,
                'taux_perte' => (float) $this->calcul->taux_perte,
                'temps_brut' => (float) $this->calcul->temps_brut,
                'temps_pause' => (float) $this->calcul->temps_pause,
                'temps_panne' => (float) $this->calcul->temps_panne,
                'temps_autre' => (float) $this->calcul->temps_autre,
                'temps_effectif' => (float) $this->calcul->temps_effectif,
                'details_json' => $this->calcul->details_json,
                'calcule_le' => $this->calcul->calcule_le?->toDateTimeString(),
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}