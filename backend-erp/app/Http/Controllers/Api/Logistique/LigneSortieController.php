<?php
// app/Http/Controllers/Api/Logistique/LigneSortieController.php

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
        $lignes = $bonsSortie->lignes()->with('classement.produit')->get();

        return $this->success($lignes);
    }

    public function store(Request $request, BonSortie $bonsSortie): JsonResponse
    {
        if ($bonsSortie->statut !== 'brouillon') {
            return $this->error('Ce bon de sortie ne peut plus être modifié.', 422);
        }

        $validated = $request->validate([
            'classement_id' => ['required', 'exists:classement_produits,id'],
            'quantite'      => ['required', 'numeric', 'min:0.001'],
        ]);

        $ligne = LigneSortie::create([
            'bon_sortie_id' => $bonsSortie->id,
            ...$validated,
        ]);

        return $this->created($ligne->load('classement.produit'));
    }

    public function show(LigneSortie $ligneSortie): JsonResponse
    {
        return $this->success($ligneSortie->load('classement.produit'));
    }

    public function update(Request $request, LigneSortie $ligneSortie): JsonResponse
    {
        if ($ligneSortie->bonSortie->statut !== 'brouillon') {
            return $this->error('Ce bon de sortie ne peut plus être modifié.', 422);
        }

        $validated = $request->validate([
            'quantite' => ['required', 'numeric', 'min:0.001'],
        ]);

        $ligneSortie->update($validated);

        return $this->success($ligneSortie->fresh(), 'Ligne mise à jour.');
    }

    public function destroy(LigneSortie $ligneSortie): JsonResponse
    {
        if ($ligneSortie->bonSortie->statut !== 'brouillon') {
            return $this->error('Ce bon de sortie ne peut plus être modifié.', 422);
        }

        $ligneSortie->delete();

        return $this->success(null, 'Ligne supprimée.');
    }
}