<?php

namespace App\Http\Controllers\Api\Logistique;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\BonSortie;
use App\Models\LigneSortie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LigneSortieController extends BaseApiController
{
    public function index(BonSortie $bonsSortie): JsonResponse
    {
        $lignes = $bonsSortie->lignes()
            ->with('produit', 'classement')
            ->get();

        return $this->success($lignes);
    }

    public function store(Request $request, BonSortie $bonsSortie): JsonResponse
    {
        if ($bonsSortie->statut !== 'brouillon') {
            return $this->error('Ce bon de sortie ne peut plus etre modifie.', 422);
        }

        $validated = $request->validate([
            'produit_id' => ['required', 'exists:produits,id'],
            'classement_id' => ['required', 'exists:classement_produits,id'],
            'quantite' => ['required', 'numeric', 'min:0.001'],
        ]);

        $ligne = LigneSortie::create([
            'bon_sortie_id' => $bonsSortie->id,
            ...$validated,
        ]);

        return $this->created($ligne->load('produit', 'classement'));
    }

    public function show(LigneSortie $ligneSortie): JsonResponse
    {
        return $this->success($ligneSortie->load('produit', 'classement'));
    }

    public function update(Request $request, LigneSortie $ligneSortie): JsonResponse
    {
        if ($ligneSortie->bonSortie->statut !== 'brouillon') {
            return $this->error('Ce bon de sortie ne peut plus etre modifie.', 422);
        }

        $validated = $request->validate([
            'produit_id' => ['sometimes', 'exists:produits,id'],
            'classement_id' => ['sometimes', 'exists:classement_produits,id'],
            'quantite' => ['required', 'numeric', 'min:0.001'],
        ]);

        $ligneSortie->update($validated);

        return $this->success(
            $ligneSortie->fresh('produit', 'classement'),
            'Ligne mise a jour.'
        );
    }

    public function destroy(LigneSortie $ligneSortie): JsonResponse
    {
        if ($ligneSortie->bonSortie->statut !== 'brouillon') {
            return $this->error('Ce bon de sortie ne peut plus etre modifie.', 422);
        }

        $ligneSortie->delete();

        return $this->success(null, 'Ligne supprimee.');
    }
}