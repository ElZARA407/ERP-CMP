<?php
// app/Http/Requests/Achat\StoreJournalAchatRequest.php

namespace App\Http\Requests\Achat;

use Illuminate\Foundation\Http\FormRequest;

class StoreJournalAchatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fournisseur_id'         => ['required', 'exists:fournisseurs,id'],
            'date'                   => ['required', 'date'],
            'location_id'            => ['required', 'exists:locations,id'],
            'vehicule'               => ['nullable', 'string', 'max:30'],
            'observations'           => ['nullable', 'string'],
            'lignes'                 => ['required', 'array', 'min:1'],
            'lignes.*.matiere_id'    => ['required', 'exists:matieres_premieres,id'],
            'lignes.*.quantite'      => ['required', 'numeric', 'min:0.001'],
            'lignes.*.prix_unitaire' => ['required', 'numeric', 'min:0'],
        ];
    }
}