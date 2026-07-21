<?php
// app/Http/Requests/Commercial/UpdateClientRequest.php

namespace App\Http\Requests\Commercial;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('client')?->id;

        return [
            'nom'            => ['sometimes', 'string', 'max:150'],
            'reference'      => ['sometimes', 'string', 'max:30', "unique:clients,reference,{$id}"],
            'est_divers'     => ['sometimes', 'boolean'],
            'NIF'            => ['nullable', 'string', 'max:50'],
            'STAT'           => ['nullable', 'string', 'max:50'],
            'adresse'        => ['sometimes', 'string'],
            'email'          => ['nullable', 'email', 'max:150'],
            'contact'        => ['sometimes', 'string', 'max:30'],
            'interlocutaire' => ['nullable', 'string', 'max:150'],
            'code_compta'    => ['nullable', 'string', 'max:20'],
            'facturation'    => ['nullable', 'string', 'max:20'],
            'actif'          => ['sometimes', 'boolean'],
        ];
    }
}