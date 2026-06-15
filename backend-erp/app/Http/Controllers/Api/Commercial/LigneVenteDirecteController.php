<?php
// app/Http/Controllers/Api/Commercial/LigneVenteDirecteController.php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\VenteDirecte;
use App\Models\LigneVenteDirecte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LigneVenteDirecteController extends BaseApiController
{
    public function index(VenteDirecte $venteDirecte): JsonResponse
    {
        $lignes = $venteDirecte->lignes()
            ->with('classement.produit')
            ->get();

        return $this->success($lignes);
    }

    public function store(Request $request, VenteDirecte $venteDirecte): JsonResponse
    {
        if ($venteDirecte->statut !== 'brouillon') {
            return $this->error('Cette vente ne peut plus être modifiée.', 422);
        }

        $validated = $request->validate([
            'classement_id' => ['required', 'exists:classement_produits,id'],
            'quantite'      => ['required', 'numeric', 'min:0.001'],
            'prix_unitaire' => ['required', 'numeric', 'min:0'],
        ]);

        $totalLigne = round($validated['quantite'] * $validated['prix_unitaire'], 2);

        $ligne = LigneVenteDirecte::create([
            'vente_directe_id' => $venteDirecte->id,
            ...$validated,
            'total_ligne' => $totalLigne,
        ]);

        $venteDirecte->update(['total' => $venteDirecte->calculerTotal()]);

        return $this->created($ligne->load('classement.produit'));
    }

    public function show(LigneVenteDirecte $ligneVenteDirecte): JsonResponse
    {
        return $this->success($ligneVenteDirecte->load('classement.produit'));
    }

    public function update(Request $request, LigneVenteDirecte $ligneVenteDirecte): JsonResponse
    {
        $validated = $request->validate([
            'quantite'      => ['sometimes', 'numeric', 'min:0.001'],
            'prix_unitaire' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $ligneVenteDirecte->update($validated);
        $totalLigne = $ligneVenteDirecte->calculerTotalLigne();
        $ligneVenteDirecte->update(['total_ligne' => $totalLigne]);

        $ligneVenteDirecte->venteDirecte->update([
            'total' => $ligneVenteDirecte->venteDirecte->calculerTotal(),
        ]);

        return $this->success($ligneVenteDirecte->fresh(), 'Ligne mise à jour.');
    }

    public function destroy(LigneVenteDirecte $ligneVenteDirecte): JsonResponse
    {
        $vente = $ligneVenteDirecte->venteDirecte;
        $ligneVenteDirecte->delete();
        $vente->update(['total' => $vente->calculerTotal()]);

        return $this->success(null, 'Ligne supprimée.');
    }
}