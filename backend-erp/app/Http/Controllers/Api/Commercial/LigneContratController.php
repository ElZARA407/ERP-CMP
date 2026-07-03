<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Contrat;
use App\Models\LigneContrat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LigneContratController extends BaseApiController
{
    public function index(Contrat $contrat): JsonResponse
    {
        $lignes = $contrat->lignes()->with('produit', 'classement')->get();

        return $this->success($lignes);
    }

    public function store(Request $request, Contrat $contrat): JsonResponse
    {
        $validated = $request->validate([
            'produit_id' => ['required', 'exists:produits,id'],
            'classement_id' => ['required', 'exists:classement_produits,id'],
            'quantite_contractuelle' => ['required', 'numeric', 'min:0.001'],
            'frequence' => ['required', 'in:hebdomadaire,bimensuel,mensuel'],
            'prix_unitaire' => ['required', 'numeric', 'min:0'],
        ]);

        $ligne = LigneContrat::create([
            'contrat_id' => $contrat->id,
            ...$validated,
            'quantite_livree_ytd' => 0,
            'statut' => 'disponible',
        ]);

        return $this->created($ligne->load('produit', 'classement'));
    }

    public function show(LigneContrat $ligneContrat): JsonResponse
    {
        return $this->success($ligneContrat->load('produit', 'classement', 'contrat'));
    }

    public function update(Request $request, LigneContrat $ligneContrat): JsonResponse
    {
        $validated = $request->validate([
            'produit_id' => ['sometimes', 'exists:produits,id'],
            'classement_id' => ['sometimes', 'exists:classement_produits,id'],
            'quantite_contractuelle' => ['sometimes', 'numeric', 'min:0.001'],
            'frequence' => ['sometimes', 'in:hebdomadaire,bimensuel,mensuel'],
            'prix_unitaire' => ['sometimes', 'numeric', 'min:0'],
            'statut' => ['sometimes', 'in:disponible,indisponible,en_cours'],
        ]);

        $ligneContrat->update($validated);

        return $this->success(
            $ligneContrat->fresh('produit', 'classement'),
            'Ligne contrat mise a jour.'
        );
    }

    public function destroy(LigneContrat $ligneContrat): JsonResponse
    {
        $ligneContrat->delete();

        return $this->success(null, 'Ligne supprimee.');
    }
}