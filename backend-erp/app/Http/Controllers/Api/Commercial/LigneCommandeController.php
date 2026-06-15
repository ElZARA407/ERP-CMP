<?php
// app/Http/Controllers/Api/Commercial/LigneCommandeController.php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\LigneCommandeResource;
use App\Models\Commande;
use App\Models\LigneCommande;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LigneCommandeController extends BaseApiController
{
    public function index(Commande $commande): JsonResponse
    {
        $lignes = $commande->lignes()
            ->with('classement.produit')
            ->get();

        return $this->success(LigneCommandeResource::collection($lignes));
    }

    public function store(Request $request, Commande $commande): JsonResponse
    {
        if (!$commande->statut->estEnCours()) {
            return $this->error('Cette commande ne peut plus être modifiée.', 422);
        }

        $validated = $request->validate([
            'classement_id' => ['required', 'exists:classement_produits,id'],
            'quantite'      => ['required', 'numeric', 'min:0.001'],
            'prix_unitaire' => ['required', 'numeric', 'min:0'],
        ]);

        $ligne = LigneCommande::create([
            'commande_id'       => $commande->id,
            ...$validated,
            'quantite_restante' => $validated['quantite'],
            'etat'              => 'disponible',
        ]);

        return $this->created(
            new LigneCommandeResource($ligne->load('classement.produit'))
        );
    }

    public function show(LigneCommande $ligneCommande): JsonResponse
    {
        return $this->success(
            new LigneCommandeResource($ligneCommande->load('classement.produit'))
        );
    }

    public function update(Request $request, LigneCommande $ligneCommande): JsonResponse
    {
        $validated = $request->validate([
            'quantite'      => ['sometimes', 'numeric', 'min:0.001'],
            'prix_unitaire' => ['sometimes', 'numeric', 'min:0'],
            'etat'          => ['sometimes', 'in:disponible,indisponible,en_cours'],
        ]);

        $ligneCommande->update($validated);

        return $this->success(
            new LigneCommandeResource($ligneCommande->fresh('classement.produit')),
            'Ligne mise à jour.'
        );
    }

    public function destroy(LigneCommande $ligneCommande): JsonResponse
    {
        if ($ligneCommande->quantite_restante < $ligneCommande->quantite) {
            return $this->error(
                'Cette ligne a déjà des livraisons partielles et ne peut pas être supprimée.',
                422
            );
        }

        $ligneCommande->delete();

        return $this->success(null, 'Ligne supprimée.');
    }
}