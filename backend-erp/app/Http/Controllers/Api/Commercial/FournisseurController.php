<?php
// app/Http/Controllers/Api/Commercial/FournisseurController.php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\FournisseurResource;
use App\Models\Fournisseur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FournisseurController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Fournisseur::query();

        if ($request->filled('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nom', 'like', "%{$request->search}%")
                  ->orWhere('reference', 'like', "%{$request->search}%");
            });
        }

        $fournisseurs = $query
            ->orderBy('nom')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            FournisseurResource::collection($fournisseurs)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'            => ['required', 'string', 'max:150'],
            'reference'      => ['required', 'string', 'max:30', 'unique:fournisseurs,reference'],
            'NIF'            => ['nullable', 'string', 'max:50'],
            'STAT'           => ['nullable', 'string', 'max:50'],
            'adresse'        => ['required', 'string'],
            'email'          => ['nullable', 'email', 'max:150'],
            'contact'        => ['required', 'string', 'max:30'],
            'interlocutaire' => ['nullable', 'string', 'max:150'],
            'code_compta'    => ['nullable', 'string', 'max:20'],
        ]);

        $fournisseur = Fournisseur::create($validated);

        return $this->created(new FournisseurResource($fournisseur));
    }

    public function show(Fournisseur $fournisseur): JsonResponse
    {
        return $this->success(new FournisseurResource($fournisseur));
    }

    public function update(Request $request, Fournisseur $fournisseur): JsonResponse
    {
        $validated = $request->validate([
            'nom'            => ['sometimes', 'string', 'max:150'],
            'NIF'            => ['nullable', 'string', 'max:50'],
            'STAT'           => ['nullable', 'string', 'max:50'],
            'adresse'        => ['sometimes', 'string'],
            'email'          => ['nullable', 'email', 'max:150'],
            'contact'        => ['sometimes', 'string', 'max:30'],
            'interlocutaire' => ['nullable', 'string', 'max:150'],
            'code_compta'    => ['nullable', 'string', 'max:20'],
            'actif'          => ['sometimes', 'boolean'],
        ]);

        $fournisseur->update($validated);

        return $this->success(
            new FournisseurResource($fournisseur->fresh()),
            'Fournisseur mis à jour.'
        );
    }

    public function destroy(Fournisseur $fournisseur): JsonResponse
    {
        $fournisseur->delete();

        return $this->success(null, 'Fournisseur archivé.');
    }
}