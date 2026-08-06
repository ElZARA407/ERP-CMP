<?php
// app/Http/Resources/BpSessionResource.php

namespace App\Http\Resources;

use App\Models\BpEvenement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BpSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_numero' => $this->session_numero,
            'date_session' => $this->date_session?->toDateString(),
            'machine_id' => $this->machine_id,
            'cout_electricite' => (float) $this->cout_electricite,
            'cout_total' => (float) $this->cout_total,
            'statut' => $this->statut,
            'machine' => $this->whenLoaded('machine', fn () => [
                'id' => $this->machine->id,
                'nom' => $this->machine->nom,
            ]),
            'matieres' => $this->whenLoaded('matieres'),
            'obtenus' => $this->whenLoaded('obtenus'),
            'employes' => $this->whenLoaded('employes'),
            'evenements' => $this->whenLoaded('evenements', fn () => $this->evenements->map(fn ($evenement) => [
                'id' => $evenement->id,
                'type_evenement' => $evenement->type_evenement,
                'heure_debut' => $evenement->heure_debut,
                'heure_fin' => $evenement->heure_fin,
                'description' => $evenement->description,
                'operateur' => $evenement->relationLoaded('operateur') ? [
                    'id' => $evenement->operateur?->id,
                    'nom' => $evenement->operateur?->nom,
                ] : null,
                'total' => $this->formatDuree($evenement),
            ])),
            'calcul' => $this->whenLoaded('calcul', fn () => [
                'id' => $this->calcul->id,
                'temps_brut' => (float) $this->calcul->temps_brut,
                'temps_pause' => (float) $this->calcul->temps_pause,
                'temps_panne' => (float) $this->calcul->temps_panne,
                'production_moyenne_heure' => (float) round($this->calcul->quantite_totale_produite / $this->calcul->temps_effectif, 2),
                'temps_effectif' => (float) $this->calcul->temps_effectif,
                'quantite_totale_produite' => (float) $this->calcul->quantite_totale_produite,
                'cout_matieres_total' => (float) $this->calcul->cout_matieres_total,
                'cout_main_oeuvre_total' => (float) $this->calcul->cout_main_oeuvre_total,
                'cout_electricite' => (float) $this->calcul->cout_electricite,
                'cout_global' => (float) $this->calcul->cout_global,
                'cout_unitaire' => (float) $this->calcul->cout_unitaire,
                'details_json' => $this->calcul->details_json,
                'calcule_le' => $this->calcul->calcule_le?->toDateTimeString(),
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }

    /**
     * Formate la durée (float en heures) en chaîne lisible "2H" ou "1H30".
     */
    private function formatDuree(BpEvenement $evenement): ?string
    {
        if (!$evenement->heure_fin) {
            return null; // événement encore en cours
        }

        $heuresDecimal = $evenement->dureeEnHeures(); // ex: 1.5

        $heures  = (int) $heuresDecimal;
        $minutes = (int) round(($heuresDecimal - $heures) * 60);

        return $minutes > 0
            ? sprintf('%dH%02d', $heures, $minutes)
            : sprintf('%dH', $heures);
    }
}