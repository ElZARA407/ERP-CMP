<?php
// app/Http/Controllers/Api/Logistique/LigneLivraisonController.php

namespace App\Http\Controllers\Api\Logistique;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Livraison;
use App\Models\LigneLivraison;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LigneLivraisonController extends BaseApiController
{
    public function index(Livraison $livraison): JsonResponse
    {
        $lignes = $livraison->lignes()
            ->with('classement.produit', 'ligneCommande', 'ligneVenteDirecte')
            ->get();

        return $this->success($lignes);
    }

    public function store(Request $request, Livraison $livraison): JsonResponse
    {
        if ($livraison->statut !== 'prepare') {
            return $this->error('Cette livraison ne peut plus être modifiée.', 422);
        }

        $validated = $request->validate([
            'ligne_commande_id'      => ['nullable', 'exists:lignes_commande,id'],
            'ligne_vente_directe_id' => ['nullable', 'exists:lignes_vente_directe,id'],
            'classement_id'          => ['required', 'exists:classement_produits,id'],
            'quantite_livree'         => ['required', 'numeric', 'min:0.001'],
        ]);

        if (empty($validated['ligne_commande_id']) && empty($validated['ligne_vente_directe_id'])) {
            return $this->error(
                'Une ligne source (commande ou vente directe) est obligatoire.',
                422
            );
        }

        $ligne = LigneLivraison::create([
            'livraison_id' => $livraison->id,
            ...$validated,
        ]);

        return $this->created($ligne->load('classement.produit'));
    }

    public function show(LigneLivraison $ligneLivraison): JsonResponse
    {
        return $this->success(
            $ligneLivraison->load('classement.produit', 'ligneCommande', 'ligneVenteDirecte')
        );
    }

    public function update(Request $request, LigneLivraison $ligneLivraison): JsonResponse
    {
        if ($ligneLivraison->livraison->statut !== 'prepare') {
            return $this->error('Cette livraison ne peut plus être modifiée.', 422);
        }

        $validated = $request->validate([
            'quantite_livree' => ['required', 'numeric', 'min:0.001'],
        ]);

        $ligneLivraison->update($validated);

        return $this->success($ligneLivraison->fresh(), 'Ligne mise à jour.');
    }

    public function destroy(LigneLivraison $ligneLivraison): JsonResponse
    {
        if ($ligneLivraison->livraison->statut !== 'prepare') {
            return $this->error('Cette livraison ne peut plus être modifiée.', 422);
        }

        $ligneLivraison->delete();

        return $this->success(null, 'Ligne supprimée.');
    }
}