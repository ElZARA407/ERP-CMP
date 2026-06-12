<?php
// app/Http/Requests/Commercial/StoreClientRequest.php

namespace App\Http\Requests\Commercial;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom'            => ['required', 'string', 'max:150'],
            'reference'      => ['required', 'string', 'max:30', 'unique:clients,reference'],
            'NIF'            => ['nullable', 'string', 'max:50'],
            'STAT'           => ['nullable', 'string', 'max:50'],
            'adresse'        => ['required', 'string'],
            'email'          => ['nullable', 'email', 'max:150'],
            'contact'        => ['required', 'string', 'max:30'],
            'interlocutaire' => ['nullable', 'string', 'max:150'],
            'code_compta'    => ['nullable', 'string', 'max:20'],
            'facturation'    => ['nullable', 'string', 'max:20'],
        ];
    }
}