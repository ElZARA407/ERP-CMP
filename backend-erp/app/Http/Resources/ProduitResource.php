<?php

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
            // Stocks groupés par qualité — utile pour afficher
            // "1ère qualité : 500 unités / 2ème qualité : 120 unités"
            'stocks_par_qualite' => $this->whenLoaded('stocks', fn() =>
                $this->stocks
                    ->groupBy('classement_id')
                    ->map(fn($groupe, $classementId) => [
                        'classement_id' => $classementId,
                        'qualite'       => $groupe->first()->classement?->qualite->value,
                        'libelle'       => $groupe->first()->classement?->label(),
                        'stock_total'   => (float) $groupe->sum('stock_total'),
                    ])
                    ->values()
            ),
            'created_at'  => $this->created_at?->toDateString(),
        ];
    }
}