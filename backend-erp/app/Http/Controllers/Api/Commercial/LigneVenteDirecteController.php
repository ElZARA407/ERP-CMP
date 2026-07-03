<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\LigneVenteDirecte;
use App\Models\VenteDirecte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LigneVenteDirecteController extends BaseApiController
{
    public function index(VenteDirecte $venteDirecte): JsonResponse
    {
        $lignes = $venteDirecte->lignes()
            ->with('produit', 'classement')
            ->get();

        return $this->success($lignes);
    }

    public function store(Request $request, VenteDirecte $venteDirecte): JsonResponse
    {
        if ($venteDirecte->statut !== 'brouillon') {
            return $this->error('Cette vente ne peut plus etre modifiee.', 422);
        }

        $validated = $request->validate([
            'produit_id' => ['required', 'exists:produits,id'],
            'classement_id' => ['required', 'exists:classement_produits,id'],
            'quantite' => ['required', 'numeric', 'min:0.001'],
            'prix_unitaire' => ['required', 'numeric', 'min:0'],
        ]);

        $totalLigne = round($validated['quantite'] * $validated['prix_unitaire'], 2);

        $ligne = LigneVenteDirecte::create([
            'vente_directe_id' => $venteDirecte->id,
            ...$validated,
            'total_ligne' => $totalLigne,
        ]);

        $venteDirecte->update(['total' => $venteDirecte->calculerTotal()]);

        return $this->created($ligne->load('produit', 'classement'));
    }

    public function show(LigneVenteDirecte $ligneVenteDirecte): JsonResponse
    {
        return $this->success($ligneVenteDirecte->load('produit', 'classement'));
    }

    public function update(Request $request, LigneVenteDirecte $ligneVenteDirecte): JsonResponse
    {
        $validated = $request->validate([
            'produit_id' => ['sometimes', 'exists:produits,id'],
            'classement_id' => ['sometimes', 'exists:classement_produits,id'],
            'quantite' => ['sometimes', 'numeric', 'min:0.001'],
            'prix_unitaire' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $ligneVenteDirecte->update($validated);

        $ligneVenteDirecte->update([
            'total_ligne' => $ligneVenteDirecte->calculerTotalLigne(),
        ]);

        $ligneVenteDirecte->venteDirecte->update([
            'total' => $ligneVenteDirecte->venteDirecte->calculerTotal(),
        ]);

        return $this->success(
            $ligneVenteDirecte->fresh('produit', 'classement'),
            'Ligne mise a jour.'
        );
    }

    public function destroy(LigneVenteDirecte $ligneVenteDirecte): JsonResponse
    {
        $vente = $ligneVenteDirecte->venteDirecte;

        $ligneVenteDirecte->delete();

        $vente->update([
            'total' => $vente->calculerTotal(),
        ]);

        return $this->success(null, 'Ligne supprimee.');
    }
}