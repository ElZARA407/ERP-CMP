<?php

namespace App\Http\Controllers\Api\Catalogue;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\ProduitResource;
use App\Models\Produit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProduitController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        // 'stocks.classement' pour avoir qualite+libelle dans stocks_par_qualite
        $query = Produit::with('categorie', 'stocks.classement');

        if ($request->filled('categorie_id')) {
            $query->where('categorie_id', $request->categorie_id);
        }

        if ($request->filled('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('designation', 'like', "%{$request->search}%")
                  ->orWhere('nomencla', 'like', "%{$request->search}%");
            });
        }

        $produits = $query
            ->orderBy('designation')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            ProduitResource::collection($produits)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        // La création de produit ne crée plus de classements
        // (les classements sont globaux, gérés par ClassementSeeder)
        $validated = $request->validate([
            'nomencla'     => ['required', 'string', 'max:30', 'unique:produits,nomencla'],
            'designation'  => ['required', 'string', 'max:150'],
            'categorie_id' => ['required', 'exists:categories_produits,id'],
            'contenance'   => ['nullable', 'string', 'max:20'],
            'format'       => ['nullable', 'string', 'max:20'],
            'unite'        => ['required', 'string', 'max:10'],
            'colisage'     => ['required', 'numeric', 'min:1'],
            'poids'        => ['required', 'string', 'max:10'],
            'seuil'        => ['nullable', 'numeric', 'min:0'],
        ]);

        $produit = Produit::create($validated);

        return $this->created(
            new ProduitResource($produit->load('categorie'))
        );
    }

    public function show(Produit $produit): JsonResponse
    {
        $produit->load('categorie', 'stocks.classement');

        return $this->success(new ProduitResource($produit));
    }

    public function update(Request $request, Produit $produit): JsonResponse
    {
        $validated = $request->validate([
            'designation'  => ['sometimes', 'string', 'max:150'],
            'contenance'   => ['nullable', 'string', 'max:20'],
            'format'       => ['nullable', 'string', 'max:20'],
            'colisage'     => ['sometimes', 'numeric', 'min:1'],
            'poids'        => ['sometimes', 'string', 'max:10'],
            'seuil'        => ['sometimes', 'numeric', 'min:0'],
            'actif'        => ['sometimes', 'boolean'],
        ]);

        $produit->update($validated);

        return $this->success(
            new ProduitResource($produit->fresh('categorie', 'stocks.classement')),
            'Produit mis à jour.'
        );
    }

    public function destroy(Produit $produit): JsonResponse
    {
        $produit->delete();

        return $this->success(null, 'Produit archivé.');
    }
}