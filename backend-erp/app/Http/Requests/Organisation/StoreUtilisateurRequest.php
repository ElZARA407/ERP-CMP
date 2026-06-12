<?php
// app/Http/Requests/Organisation/StoreUtilisateurRequest.php

namespace App\Http\Requests\Organisation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUtilisateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'nom'         => ['required', 'string', 'max:150'],
            'email'       => ['required', 'email', 'unique:utilisateurs,email'],
            'password'    => ['required', Password::min(8)->letters()->numbers()],
            'role_id'     => ['required', 'exists:roles,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'actif'       => ['boolean'],
        ];
    }
}