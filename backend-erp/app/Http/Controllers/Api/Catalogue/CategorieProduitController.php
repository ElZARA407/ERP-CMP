<?php
// app/Http/Controllers/Api/Catalogue/CategorieProduitController.php

namespace App\Http\Controllers\Api\Catalogue;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\CategorieProduitResource;
use App\Models\CategorieProduit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategorieProduitController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $categories = CategorieProduit::withCount('produits')->orderBy('nom')->get();

        return $this->success(CategorieProduitResource::collection($categories));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom' => ['required', 'in:INJ,HDPE,PET,MCH', 'unique:categorie_produits,nom'],
        ]);

        $categorie = CategorieProduit::create($validated);

        return $this->created(new CategorieProduitResource($categorie));
    }

    public function show(CategorieProduit $category): JsonResponse
    {
        $category->loadCount('produits');

        return $this->success(new CategorieProduitResource($category));
    }

    public function update(Request $request, CategorieProduit $category): JsonResponse
    {
        return $this->error('Les catégories ne peuvent pas être modifiées.', 422);
    }

    public function destroy(CategorieProduit $category): JsonResponse
    {
        if ($category->produits()->exists()) {
            return $this->error(
                'Cette catégorie contient des produits et ne peut pas être supprimée.',
                422
            );
        }

        $category->delete();

        return $this->success(null, 'Catégorie supprimée.');
    }
}