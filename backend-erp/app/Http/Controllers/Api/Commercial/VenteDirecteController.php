<?php
// app/Http/Controllers/Api/Commercial/VenteDirecteController.php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\VenteDirecteResource;
use App\Models\VenteDirecte;
use App\Models\LigneVenteDirecte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VenteDirecteController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = VenteDirecte::with('client', 'location', 'createur');

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('date_debut')) {
            $query->where('date', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->where('date', '<=', $request->date_fin);
        }

        $ventes = $query
            ->orderByDesc('date')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            VenteDirecteResource::collection($ventes)->response()->getData(true)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id'              => ['required', 'exists:clients,id'],
            'date'                   => ['required', 'date'],
            'location_id'            => ['required', 'exists:locations,id'],
            'lignes'                 => ['required', 'array', 'min:1'],
            'lignes.*.classement_id' => ['required', 'exists:classement_produits,id'],
            'lignes.*.quantite'      => ['required', 'numeric', 'min:0.001'],
            'lignes.*.prix_unitaire' => ['required', 'numeric', 'min:0'],
        ]);

        $vente = DB::transaction(function () use ($validated) {
            $lignes = $validated['lignes'];
            unset($validated['lignes']);

            $total = array_sum(array_map(
                fn($l) => round($l['quantite'] * $l['prix_unitaire'], 2),
                $lignes
            ));

            $vente = VenteDirecte::create([
                'numero'     => VenteDirecte::generateReference('VD'),
                ...$validated,
                'statut'     => 'brouillon',
                'total'      => $total,
                'created_by' => $request->user()->id,
            ]);

            foreach ($lignes as $ligne) {
                LigneVenteDirecte::create([
                    'vente_directe_id' => $vente->id,
                    'classement_id'    => $ligne['classement_id'],
                    'quantite'         => $ligne['quantite'],
                    'prix_unitaire'    => $ligne['prix_unitaire'],
                    'total_ligne'      => round($ligne['quantite'] * $ligne['prix_unitaire'], 2),
                ]);
            }

            return $vente->load('client', 'location', 'lignes.classement.produit');
        });

        return $this->created(new VenteDirecteResource($vente));
    }

    public function show(VenteDirecte $venteDirecte): JsonResponse
    {
        $venteDirecte->load('client', 'location', 'lignes.classement.produit', 'createur');

        return $this->success(new VenteDirecteResource($venteDirecte));
    }

    public function update(Request $request, VenteDirecte $venteDirecte): JsonResponse
    {
        if ($venteDirecte->statut !== 'brouillon') {
            return $this->error('Cette vente ne peut plus être modifiée.', 422);
        }

        $venteDirecte->update($request->only(['date', 'location_id']));

        return $this->success(
            new VenteDirecteResource($venteDirecte->fresh('client', 'location')),
            'Vente directe mise à jour.'
        );
    }

    public function destroy(VenteDirecte $venteDirecte): JsonResponse
    {
        if ($venteDirecte->statut !== 'brouillon') {
            return $this->error('Seule une vente en brouillon peut être supprimée.', 422);
        }

        $venteDirecte->delete();

        return $this->success(null, 'Vente directe supprimée.');
    }

    public function valider(VenteDirecte $venteDirecte): JsonResponse
    {
        if ($venteDirecte->statut !== 'brouillon') {
            return $this->error('Cette vente est déjà validée.', 422);
        }

        if ($venteDirecte->lignes()->count() === 0) {
            return $this->error('Impossible de valider une vente sans lignes.', 422);
        }

        $venteDirecte->update(['statut' => 'validee']);

        return $this->success(
            new VenteDirecteResource($venteDirecte->fresh()),
            'Vente directe validée.'
        );
    }
}