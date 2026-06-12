<?php
// app/Http/Requests/Finance/PayerFactureRequest.php

namespace App\Http\Requests\Finance;

use App\Enums\ModePaiement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayerFactureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode_paiement' => [
                'required',
                Rule::enum(ModePaiement::class),
            ],
        ];
    }
}