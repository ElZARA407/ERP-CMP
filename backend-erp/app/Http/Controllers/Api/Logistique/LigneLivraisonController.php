<?php

namespace App\Http\Controllers\Api\Logistique;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\LigneLivraison;
use App\Models\Livraison;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LigneLivraisonController extends BaseApiController
{
    public function index(Livraison $livraison): JsonResponse
    {
        $lignes = $livraison->lignes()
            ->with('produit', 'classement', 'ligneCommande', 'ligneVenteDirecte')
            ->get();

        return $this->success($lignes);
    }

    public function store(Request $request, Livraison $livraison): JsonResponse
    {
        if ($livraison->statut !== 'prepare') {
            return $this->error('Cette livraison ne peut plus etre modifiee.', 422);
        }

        $validated = $request->validate([
            'ligne_commande_id' => ['nullable', 'exists:ligne_commandes,id'],
            'ligne_vente_directe_id' => ['nullable', 'exists:lignes_vente_directe,id'],
            'produit_id' => ['required', 'exists:produits,id'],
            'classement_id' => ['required', 'exists:classement_produits,id'],
            'quantite_livree' => ['required', 'numeric', 'min:0.001'],
        ]);

        if (empty($validated['ligne_commande_id']) && empty($validated['ligne_vente_directe_id'])) {
            return $this->error(
                'Une ligne source commande ou vente directe est obligatoire.',
                422
            );
        }

        if (!empty($validated['ligne_commande_id']) && !empty($validated['ligne_vente_directe_id'])) {
            return $this->error(
                'Une seule ligne source est autorisee.',
                422
            );
        }

        $ligne = LigneLivraison::create([
            'livraison_id' => $livraison->id,
            ...$validated,
        ]);

        return $this->created($ligne->load('produit', 'classement', 'ligneCommande', 'ligneVenteDirecte'));
    }

    public function show(LigneLivraison $ligneLivraison): JsonResponse
    {
        return $this->success(
            $ligneLivraison->load('produit', 'classement', 'ligneCommande', 'ligneVenteDirecte')
        );
    }

    public function update(Request $request, LigneLivraison $ligneLivraison): JsonResponse
    {
        if ($ligneLivraison->livraison->statut !== 'prepare') {
            return $this->error('Cette livraison ne peut plus etre modifiee.', 422);
        }

        $validated = $request->validate([
            'produit_id' => ['sometimes', 'exists:produits,id'],
            'classement_id' => ['sometimes', 'exists:classement_produits,id'],
            'quantite_livree' => ['sometimes', 'numeric', 'min:0.001'],
        ]);

        $ligneLivraison->update($validated);

        return $this->success(
            $ligneLivraison->fresh('produit', 'classement', 'ligneCommande', 'ligneVenteDirecte'),
            'Ligne mise a jour.'
        );
    }

    public function destroy(LigneLivraison $ligneLivraison): JsonResponse
    {
        if ($ligneLivraison->livraison->statut !== 'prepare') {
            return $this->error('Cette livraison ne peut plus etre modifiee.', 422);
        }

        $ligneLivraison->delete();

        return $this->success(null, 'Ligne supprimee.');
    }
}