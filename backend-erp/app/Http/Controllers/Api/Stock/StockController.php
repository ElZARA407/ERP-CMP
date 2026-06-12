<?php
// app/Http/Controllers/Api/Stock/StockController.php

namespace App\Http\Controllers\Api\Stock;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\StockResource;
use App\Models\Stock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Stock::with('location', 'classement.produit')
                      ->where('stock_total', '>', 0);

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->filled('entite_type')) {
            $query->where('entite_type', $request->entite_type);
        }

        $stocks = $query->paginate($request->get('per_page', config('api.per_page')));

        return $this->success(
            StockResource::collection($stocks)->response()->getData(true)
        );
    }

    public function ruptures(): JsonResponse
    {
        $stocks = Stock::enRupture()
            ->with('location', 'classement.produit')
            ->get();

        return $this->success(StockResource::collection($stocks));
    }

    public function parLocation(int $id): JsonResponse
    {
        $stocks = Stock::with('classement.produit')
                       ->where('location_id', $id)
                       ->where('stock_total', '>', 0)
                       ->get();

        return $this->success(StockResource::collection($stocks));
    }

    public function parProduit(int $id): JsonResponse
    {
        $stocks = Stock::with('location', 'classement')
                       ->where('entite_type', 'produit')
                       ->where('entite_id', $id)
                       ->get();

        return $this->success(StockResource::collection($stocks));
    }

    public function parMatiere(int $id): JsonResponse
    {
        $stocks = Stock::with('location')
                       ->where('entite_type', 'matiere')
                       ->where('entite_id', $id)
                       ->get();

        return $this->success(StockResource::collection($stocks));
    }
}