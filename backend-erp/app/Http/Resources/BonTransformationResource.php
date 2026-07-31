<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BonTransformationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'date' => $this->date?->toDateString(),
            'machine_broyage' => $this->machine_broyage,
            'machine_id' => $this->machine_id,
            'machine' => $this->whenLoaded('machine', fn () => [
                'id' => $this->machine->id,
                'nom' => $this->machine->nom,
            ]),
            'quantite_entree' => (float) $this->quantite_entree,
            'quantite_nette_consomme' => $this->quantiteNetteConsommeeTotale(),
            'quantite_broyee' => $this->quantiteBroyeeTotale(),
            'perte' => $this->perteTotale(),
            'taux_rendement' => $this->tauxRendementGlobal(),
            'taux_perte' => $this->tauxPerteGlobal(),
            'taux_avancement' => $this->tauxAvancement(),
            'observations' => $this->observations,
            'statut' => [
                'valeur' => $this->statut->value,
                'libelle' => $this->statut->label(),
            ],
            'location' => $this->whenLoaded('location', fn () => [
                'id' => $this->location->id,
                'nom' => $this->location->nom,
            ]),
            'matiere_brute' => $this->whenLoaded('matiereBrute', fn () => [
                'id' => $this->matiereBrute->id,
                'nom' => $this->matiereBrute->nom,
                'reference' => $this->matiereBrute->reference,
                'type' => $this->matiereBrute->type,
            ]),
            'sessions' => BtSessionResource::collection($this->whenLoaded('sessions')),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}