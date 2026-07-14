<?php

namespace App\Http\Controllers\Api\Production;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\BonProduction;
use App\Models\Produit;
use App\Services\ProductionCostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionCostController extends BaseApiController
{
    public function __construct(
        private readonly ProductionCostService $productionCostService
    ) {}

    public function parProduit(Request $request, Produit $produit): JsonResponse
    {
        $validated = $request->validate([
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
        ]);

        $resultat = $this->productionCostService->calculateWeightedAverageForProduct(
            $produit->id,
            $validated['date_debut'] ?? null,
            $validated['date_fin'] ?? null
        );

        return $this->success([
            'produit' => [
                'id' => $produit->id,
                'nomencla' => $produit->nomencla,
                'designation' => $produit->designation,
            ],
            ...$resultat,
        ]);
    }

    public function parBonProduction(BonProduction $bonsProduction): JsonResponse
    {
        $resultat = $this->productionCostService->calculateWeightedAverageForBp($bonsProduction);

        return $this->success($resultat);
    }
}
