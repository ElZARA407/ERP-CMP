<?php
// app/Http/Resources/PosteResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'nom'             => $this->nom,
            'taux_horaire'    => (float) $this->taux_horaire,
            'salaire_mensuel' => $this->salaire_mensuel ? (float) $this->salaire_mensuel : null,
            'cout_journalier' => $this->coutJournalier(),
            'employes_count'  => $this->whenCounted('employes'),
            'created_at'      => $this->created_at?->toDateString(),
        ];
    }
}