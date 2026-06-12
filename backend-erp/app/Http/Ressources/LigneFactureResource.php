<?php
// app/Http/Resources/LigneFactureResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LigneFactureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'quantite'      => (float) $this->quantite,
            'prix_unitaire' => (float) $this->prix_unitaire,
            'total_ligne'   => (float) $this->total_ligne,
            'classement'    => $this->whenLoaded('classement', fn() => [
                'id'          => $this->classement->id,
                'designation' => $this->classement->designation(),
            ]),
        ];
    }
}