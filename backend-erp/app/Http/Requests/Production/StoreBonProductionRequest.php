<?php
// app/Http/Requests/Production\StoreBonProductionRequest.php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class StoreBonProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'location_id' => ['required', 'exists:locations,id'],
            'produit_id' => ['required', 'exists:produits,id'],
            'machine_id' => ['required', 'exists:machines,id'],
            'quantite_cible' => ['required', 'numeric', 'min:0.001'],
        ];
    }
}