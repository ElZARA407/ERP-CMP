<?php

namespace App\Http\Controllers\Api\Catalogue;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\ClassementProduitResource;
use App\Models\ClassementProduit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassementProduitController extends BaseApiController
{
    /**
     * Liste tous les classements (référentiel global)
     */
    public function index(): JsonResponse
    {
        $classements = ClassementProduit::orderBy('qualite')->get();

        return $this->success(ClassementProduitResource::collection($classements));
    }

    /**
     * Création d'un classement (normalement géré par le seeder uniquement,
     * mais on garde la route pour les cas d'admin)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qualite'  => ['required', 'in:1er,2e,casse', 'unique:classement_produits,qualite'],
            'libelle'  => ['nullable', 'string', 'max:100'],
        ]);

        $classement = ClassementProduit::create([...$validated, 'actif' => true]);

        return $this->created(new ClassementProduitResource($classement));
    }

    public function show(ClassementProduit $classement): JsonResponse
    {
        return $this->success(new ClassementProduitResource($classement));
    }

    public function update(Request $request, ClassementProduit $classement): JsonResponse
    {
        $validated = $request->validate([
            'libelle' => ['nullable', 'string', 'max:100'],
            'actif'   => ['sometimes', 'boolean'],
        ]);

        $classement->update($validated);

        return $this->success(
            new ClassementProduitResource($classement->fresh()),
            'Classement mis à jour.'
        );
    }

    /**
     * Désactivation douce — on ne supprime jamais un classement
     * car il peut être référencé dans les stocks historiques
     */
    public function destroy(ClassementProduit $classement): JsonResponse
    {
        $classement->update(['actif' => false]);

        return $this->success(null, 'Classement désactivé.');
    }
}