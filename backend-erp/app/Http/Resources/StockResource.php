<?php
// app/Http/Resources/StockResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'entite_type'  => $this->getRawOriginal('entite_type'),
            'entite_id'    => $this->entite_id,
            'stock_total'  => (float) $this->stock_total,
            'en_rupture'   => $this->estEnRupture(),
            'location'     => $this->whenLoaded('location', fn() => [
                'id'   => $this->location->id,
                'nom'  => $this->location->nom,
                'type' => $this->location->type,
            ]),
            'classement'   => $this->whenLoaded('classement', fn() => [
                'id'          => $this->classement->id,
                'qualite'     => $this->classement->qualite->value,
                'designation' => $this->classement->designation(),
            ]),
            'updated_at'   => $this->updated_at?->toDateTimeString(),
        ];
    }
}