<?php
// app/Http/Requests/Organisation/UpdateUtilisateurRequest.php

namespace App\Http\Requests\Organisation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUtilisateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $id = $this->route('utilisateur')?->id;

        return [
            'nom'         => ['sometimes', 'string', 'max:150'],
            'email'       => ['sometimes', 'email', "unique:utilisateurs,email,{$id}"],
            'role_id'     => ['sometimes', 'exists:roles,id'],
            'location_id' => ['sometimes', 'exists:locations,id'],
            'actif'       => ['sometimes', 'boolean'],
        ];
    }
}