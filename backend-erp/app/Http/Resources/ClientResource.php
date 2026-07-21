<?php
// app/Http/Resources/ClientResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'nom'            => $this->nom,
            'reference'      => $this->reference,
            'NIF'            => $this->NIF,
            'STAT'           => $this->STAT,
            'adresse'        => $this->adresse,
            'email'          => $this->email,
            'contact'        => $this->contact,
            'interlocutaire' => $this->interlocutaire,
            'code_compta'    => $this->code_compta,
            'facturation'    => $this->facturation,
            'actif'          => $this->actif,
            'est_divers'     => $this->est_divers,
            'created_at'     => $this->created_at?->toDateString(),
        ];
    }
}