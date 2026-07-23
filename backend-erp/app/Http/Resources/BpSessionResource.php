<?php
// app/Http/Resources/BpSessionResource.php

namespace App\Http\Resources;

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
            'evenements' => $this->whenLoaded('evenements'),
            'calcul' => $this->whenLoaded('calcul', fn () => [
                'id' => $this->calcul->id,
                'temps_brut' => (float) $this->calcul->temps_brut,
                'temps_pause' => (float) $this->calcul->temps_pause,
                'temps_panne' => (float) $this->calcul->temps_panne,
                'production_moyenne_heure'=>(float) round($this->calcul->quantite_totale_produite/$this->calcul->temps_effectif,2),
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
    
}
