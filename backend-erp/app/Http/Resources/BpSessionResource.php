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
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}