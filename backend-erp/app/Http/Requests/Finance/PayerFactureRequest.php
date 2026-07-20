<?php

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

    protected function prepareForValidation(): void
    {
        if ($this->filled('mode_paiement')) {
            $this->merge([
                'mode_paiement' => trim((string) $this->input('mode_paiement')),
            ]);
        }

        if ($this->filled('montant_paye')) {
            $this->merge([
                'montant_paye' => str_replace(',', '.', (string) $this->input('montant_paye')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'mode_paiement' => [
                'required',
                'string',
                Rule::in(array_map(
                    static fn (ModePaiement $mode) => $mode->value,
                    ModePaiement::cases()
                )),
            ],
            'montant_paye' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}