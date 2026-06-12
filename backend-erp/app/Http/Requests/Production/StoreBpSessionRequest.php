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
            'date_session'       => ['required', 'date'],
            'machine_production' => ['required', 'string', 'max:100'],
            'cout_electricite'   => ['nullable', 'numeric', 'min:0'],
        ];
    }
}