<?php
// app/Http/Controllers/Api/Stock/MouvementStockController.php

namespace App\Http\Controllers\Api\Stock;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\MouvementStockResource;
use App\Models\MouvementStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MouvementStockController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = MouvementStock::with('location', 'utilisateur', 'classement.produit');

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->filled('entite_type')) {
            $query->where('entite_type', $request->entite_type);
        }

        if ($request->filled('entite_id')) {
            $query->where('entite_id', $request->entite_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }

        if ($request->filled('date_debut')) {
            $query->where('date_mouvement', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->where('date_mouvement', '<=', $request->date_fin . ' 23:59:59');
        }

        $mouvements = $query
            ->orderByDesc('date_mouvement')
            ->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            MouvementStockResource::collection($mouvements)->response()->getData(true)
        );
    }

    public function show(int $id): JsonResponse
    {
        $mouvement = MouvementStock::with(
            'location', 'utilisateur', 'classement.produit'
        )->findOrFail($id);

        return $this->success(new MouvementStockResource($mouvement));
    }
}