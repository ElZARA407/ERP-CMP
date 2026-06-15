<?php
// app/Http/Controllers/Api/Catalogue/ClassementProduitController.php

namespace App\Http\Controllers\Api\Catalogue;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\ClassementProduitResource;
use App\Models\ClassementProduit;
use App\Models\Produit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassementProduitController extends BaseApiController
{
    public function index(Produit $produit): JsonResponse
    {
        $classements = $produit->classements()->orderBy('qualite')->get();

        return $this->success(ClassementProduitResource::collection($classements));
    }

    public function store(Request $request, Produit $produit): JsonResponse
    {
        $validated = $request->validate([
            'qualite'         => [
                'required',
                'in:1er,2e,casse',
                function ($attribute, $value, $fail) use ($produit) {
                    if ($produit->classements()->where('qualite', $value)->exists()) {
                        $fail("Ce produit a déjà un classement '{$value}'.");
                    }
                },
            ],
            'prix_specifique' => ['nullable', 'numeric', 'min:0'],
        ]);

        $classement = ClassementProduit::create([
            'produit_id' => $produit->id,
            ...$validated,
            'actif'      => true,
        ]);

        return $this->created(new ClassementProduitResource($classement));
    }

    public function show(ClassementProduit $classement): JsonResponse
    {
        $classement->load('produit');

        return $this->success(new ClassementProduitResource($classement));
    }

    public function update(Request $request, ClassementProduit $classement): JsonResponse
    {
        $validated = $request->validate([
            'prix_specifique' => ['nullable', 'numeric', 'min:0'],
            'actif'           => ['sometimes', 'boolean'],
        ]);

        $classement->update($validated);

        return $this->success(
            new ClassementProduitResource($classement->fresh()),
            'Classement mis à jour.'
        );
    }

    public function destroy(ClassementProduit $classement): JsonResponse
    {
        $classement->update(['actif' => false]);

        return $this->success(null, 'Classement désactivé.');
    }
}