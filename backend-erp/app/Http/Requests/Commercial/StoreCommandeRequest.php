<?php

namespace App\Http\Requests\Commercial;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id'              => ['required', 'exists:clients,id'],
            'date'                   => ['required', 'date'],
            'date_livraison_prevue'  => ['nullable', 'date', 'after_or_equal:date'],
            'location_id'            => ['required', 'exists:locations,id'],
            'echeance'               => ['required', 'integer', 'in:15,30,60'],
            'lignes'                 => ['required', 'array', 'min:1'],
            'lignes.*.produit_id'    => ['required', 'exists:produits,id'],
            'lignes.*.classement_id' => ['required', 'exists:classement_produits,id'],
            'lignes.*.quantite'      => ['required', 'numeric', 'min:0.001'],
            'lignes.*.prix_unitaire' => ['required', 'numeric', 'min:0'],
        ];
    }
}