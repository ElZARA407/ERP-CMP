<?php
// app/Http/Requests/Finance/StoreFactureRequest.php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreFactureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'livraison_id' => [
                'required',
                'exists:livraisons,id',
                function ($attribute, $value, $fail) {
                    $livraison = \App\Models\Livraison::find($value);
                    if ($livraison && $livraison->statut !== 'livre') {
                        $fail('La livraison doit être confirmée avant facturation.');
                    }
                    if ($livraison && $livraison->estFacturee()) {
                        $fail('Cette livraison est déjà facturée.');
                    }
                },
            ],
        ];
    }
}