<?php
// app/Http/Resources/BtSessionResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BtSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'session_numero'  => $this->session_numero,
            'date_session'    => $this->date_session?->toDateString(),
            'machine_broyage' => $this->machine_broyage,
            'ecarts'          => (float) $this->ecarts,
            'statut'          => $this->statut,
            'quantite_entree' => $this->quantiteEntree(),
            'quantite_sortie' => $this->quantiteSortie(),
            'matieres'        => $this->whenLoaded('matieres', fn() =>
                $this->matieres->map(fn($m) => [
                    'id'                 => $m->id,
                    'type'               => $m->type,
                    'quantite'           => (float) $m->quantite,
                    'quantite_restituee' => (float) $m->quantite_restituee,
                    'matiere'            => [
                        'id'        => $m->matiere->id,
                        'nom'       => $m->matiere->nom,
                        'reference' => $m->matiere->reference,
                    ],
                ])
            ),
            'employes'        => $this->whenLoaded('employes', fn() =>
                $this->employes->map(fn($e) => [
                    'id'                => $e->id,
                    'heures_brutes'     => (float) $e->heures_brutes,
                    'heures_effectives' => (float) $e->heures_effectives,
                    'taux_horaire'      => (float) $e->taux_horaire,
                    'cout'              => (float) $e->cout,
                    'employe'           => [
                        'id'        => $e->employe->id,
                        'nom_complet'=> $e->employe->nomComplet(),
                    ],
                ])
            ),
            'evenements'      => $this->whenLoaded('evenements'),
            'created_at'      => $this->created_at?->toDateTimeString(),
        ];
    }
}