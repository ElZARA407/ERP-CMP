<?php
// app/Http/Controllers/Api/Rh/PosteController.php

namespace App\Http\Controllers\Api\Rh;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\PosteResource;
use App\Models\Poste;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosteController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Poste::withCount('employes');

        if ($request->filled('search')) {
            $query->where('nom', 'like', "%{$request->search}%");
        }

        $postes = $query->orderBy('nom')->get();

        return $this->success(PosteResource::collection($postes));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'             => ['required', 'string', 'max:100'],
            'taux_horaire'    => ['required', 'numeric', 'min:0'],
            'salaire_mensuel' => ['nullable', 'numeric', 'min:0'],
        ]);

        $poste = Poste::create($validated);

        return $this->created(new PosteResource($poste));
    }

    public function show(Poste $poste): JsonResponse
    {
        $poste->loadCount('employes');

        return $this->success(new PosteResource($poste));
    }

    public function update(Request $request, Poste $poste): JsonResponse
    {
        $validated = $request->validate([
            'nom'             => ['sometimes', 'string', 'max:100'],
            'taux_horaire'    => ['sometimes', 'numeric', 'min:0'],
            'salaire_mensuel' => ['nullable', 'numeric', 'min:0'],
        ]);

        $poste->update($validated);

        return $this->success(new PosteResource($poste->fresh()), 'Poste mis à jour.');
    }

    public function destroy(Poste $poste): JsonResponse
    {
        if ($poste->employes()->exists()) {
            return $this->error(
                'Ce poste ne peut pas être supprimé : des employés y sont rattachés.',
                422
            );
        }

        $poste->delete();

        return $this->success(null, 'Poste supprimé.');
    }
}