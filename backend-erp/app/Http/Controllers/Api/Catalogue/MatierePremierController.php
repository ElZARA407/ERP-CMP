<?php
// app/Http/Controllers/Api/Catalogue/MatierePremierController.php

namespace App\Http\Controllers\Api\Catalogue;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\MatierePremiereResource;
use App\Models\MatierePremiere;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatierePremierController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = MatierePremiere::query();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nom', 'like', "%{$request->search}%")
                  ->orWhere('reference', 'like', "%{$request->search}%");
            });
        }

        $matieres = $query
            ->orderBy('nom')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            MatierePremiereResource::collection($matieres)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference'   => ['required', 'string', 'max:30', 'unique:matieres_premieres,reference'],
            'nom'         => ['required', 'string', 'max:150'],
            'type'        => ['required', 'in:preformes,broyee,brute,vierge,colorant,autre'],
            'description' => ['nullable', 'string'],
            'unite'       => ['required', 'string', 'max:10'],
            'prix_moyen'  => ['nullable', 'numeric', 'min:0'],
            'actif'       => ['boolean'],
        ]);

        $matiere = MatierePremiere::create($validated);

        return $this->created(new MatierePremiereResource($matiere));
    }

    public function show(MatierePremiere $matieresPremiere): JsonResponse
    {
        return $this->success(new MatierePremiereResource($matieresPremiere));
    }

    public function update(Request $request, MatierePremiere $matieresPremiere): JsonResponse
    {
        $validated = $request->validate([
            'nom'         => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'unite'       => ['sometimes', 'string', 'max:10'],
            'actif'       => ['sometimes', 'boolean'],
        ]);

        $matieresPremiere->update($validated);

        return $this->success(
            new MatierePremiereResource($matieresPremiere->fresh()),
            'Matière première mise à jour.'
        );
    }

    public function destroy(MatierePremiere $matieresPremiere): JsonResponse
    {
        $matieresPremiere->delete();

        return $this->success(null, 'Matière première archivée.');
    }
}