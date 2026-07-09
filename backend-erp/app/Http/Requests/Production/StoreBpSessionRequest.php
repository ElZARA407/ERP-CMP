<?php
// app/Http/Requests/Production/StoreBpSessionRequest.php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class StoreBpSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_session' => ['required', 'date'],
            'machine_id' => ['required', 'exists:machines,id'],
            'cout_electricite' => ['nullable', 'numeric', 'min:0'],

            'matieres' => ['sometimes', 'array'],
            'matieres.*.matiere_id' => ['required', 'exists:matieres_premieres,id'],
            'matieres.*.quantite_utilisee' => ['required', 'numeric', 'min:0.001'],
            'matieres.*.quantite_restituee' => ['nullable', 'numeric', 'min:0'],

            'obtenus' => ['sometimes', 'array'],
            'obtenus.*.produit_id' => ['required', 'exists:produits,id'],
            'obtenus.*.classement_id' => ['required', 'exists:classement_produits,id'],
            'obtenus.*.quantite_produite' => ['required', 'numeric', 'min:0.001'],
            'obtenus.*.destination_location_id' => ['required', 'exists:locations,id'],

            'employes' => ['sometimes', 'array'],
            'employes.*.employe_id' => ['required', 'exists:employes,id'],
            'employes.*.heures_brutes' => ['required', 'numeric', 'min:0.1'],

            'evenements' => ['sometimes', 'array'],
            'evenements.*.type_evenement' => ['required', 'in:debut,fin,panne,autre'],
            'evenements.*.heure_debut' => ['required', 'date_format:H:i'],
            'evenements.*.heure_fin' => ['nullable', 'date_format:H:i'],
            'evenements.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }
}