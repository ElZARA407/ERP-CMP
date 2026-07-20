<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreFactureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $livraisonIds = $this->input('livraison_ids', []);

        if ((! is_array($livraisonIds) || $livraisonIds === []) && $this->filled('livraison_id')) {
            $livraisonIds = [$this->input('livraison_id')];
        }

        if (! is_array($livraisonIds)) {
            $livraisonIds = [$livraisonIds];
        }

        $livraisonIds = collect($livraisonIds)
            ->map(static fn ($value) => is_numeric($value) ? (int) $value : 0)
            ->filter(static fn ($value) => $value > 0)
            ->unique()
            ->values()
            ->all();

        $lignes = $this->input('lignes', []);

        if (! is_array($lignes)) {
            $lignes = [];
        }

        $lignes = collect($lignes)
            ->map(function ($ligne) {
                $ligneId = $ligne['ligne_id'] ?? $ligne['id'] ?? null;
                $prix = $ligne['prix_unitaire'] ?? null;

                $prixNormalise = str_replace(',', '.', (string) $prix);

                return [
                    'ligne_id' => is_numeric($ligneId) ? (int) $ligneId : null,
                    'prix_unitaire' => is_numeric($prixNormalise) ? (float) $prixNormalise : null,
                ];
            })
            ->filter(static fn (array $ligne) => $ligne['ligne_id'] !== null && $ligne['prix_unitaire'] !== null)
            ->values()
            ->all();

        $this->merge([
            'livraison_ids' => $livraisonIds,
            'lignes' => $lignes,
        ]);
    }

    public function rules(): array
    {
        return [
            'livraison_ids' => ['required', 'array', 'min:1'],
            'livraison_ids.*' => ['integer', 'distinct', 'exists:livraisons,id'],
            'livraison_id' => ['nullable', 'integer', 'exists:livraisons,id'],

            'lignes' => ['sometimes', 'array'],
            'lignes.*.ligne_id' => ['required', 'integer', 'distinct', 'exists:lignes_livraison,id'],
            'lignes.*.prix_unitaire' => ['required', 'numeric', 'min:0'],
        ];
    }
}