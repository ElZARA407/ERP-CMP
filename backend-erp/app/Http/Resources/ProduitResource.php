<?php
// app/Http/Resources/ProduitResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProduitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'nomencla'    => $this->nomencla,
            'designation' => $this->designation,
            'contenance'  => $this->contenance,
            'format'      => $this->format,
            'unite'       => $this->unite,
            'colisage'    => (float) $this->colisage,
            'poids'       => $this->poids,
            'seuil'       => (float) $this->seuil,
            'actif'       => $this->actif,
            'categorie'   => $this->whenLoaded('categorie', fn() => [
                'id'  => $this->categorie->id,
                'nom' => $this->categorie->nom,
            ]),
            'classements' => $this->whenLoaded('classements', fn() =>
                $this->classements->map(fn($c) => [
                    'id'              => $c->id,
                    'qualite'         => $c->qualite->value,
                    'qualite_libelle' => $c->qualite->label(),
                    'prix_specifique' => $c->prix_specifique
                        ? (float) $c->prix_specifique
                        : null,
                    'actif'           => $c->actif,
                ])
            ),
            'created_at'  => $this->created_at?->toDateString(),
        ];
    }
}