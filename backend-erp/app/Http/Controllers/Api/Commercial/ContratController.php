<?php
// app/Http/Controllers/Api/Commercial/ContratController.php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\ContratResource;
use App\Models\Contrat;
use App\Models\LigneContrat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContratController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Contrat::with('client', 'lignes.classement.produit');

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('mois')) {
            $query->where('mois', $request->mois);
        }

        if ($request->filled('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }

        $contrats = $query
            ->orderByDesc('mois')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            ContratResource::collection($contrats)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id'                      => ['required', 'exists:clients,id'],
            'mois'                           => [
                'required',
                'string',
                'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
            ],
            'lignes'                         => ['required', 'array', 'min:1'],
            'lignes.*.classement_id'         => ['required', 'exists:classement_produits,id'],
            'lignes.*.quantite_contractuelle' => ['required', 'numeric', 'min:0.001'],
            'lignes.*.frequence'             => ['required', 'in:hebdomadaire,bimensuel,mensuel'],
            'lignes.*.prix_unitaire'         => ['required', 'numeric', 'min:0'],
        ]);

        $contrat = DB::transaction(function () use ($validated) {
            $lignes = $validated['lignes'];
            unset($validated['lignes']);

            $contrat = Contrat::create([
                'numero'    => Contrat::generateReference('CTR'),
                ...$validated,
                'actif'     => true,
            ]);

            foreach ($lignes as $ligne) {
                LigneContrat::create([
                    'contrat_id' => $contrat->id,
                    ...$ligne,
                    'quantite_livree_ytd' => 0,
                    'statut'             => 'disponible',
                ]);
            }

            return $contrat->load('client', 'lignes.classement.produit');
        });

        return $this->created(new ContratResource($contrat));
    }

    public function show(Contrat $contrat): JsonResponse
    {
        $contrat->load('client', 'lignes.classement.produit');

        return $this->success(new ContratResource($contrat));
    }

    public function update(Request $request, Contrat $contrat): JsonResponse
    {
        $validated = $request->validate([
            'actif' => ['sometimes', 'boolean'],
        ]);

        $contrat->update($validated);

        return $this->success(
            new ContratResource($contrat->fresh('client', 'lignes')),
            'Contrat mis à jour.'
        );
    }

    public function destroy(Contrat $contrat): JsonResponse
    {
        $contrat->update(['actif' => false]);

        return $this->success(null, 'Contrat désactivé.');
    }
}