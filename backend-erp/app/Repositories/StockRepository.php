<?php
// app/Repositories/StockRepository.php

namespace App\Repositories;

use App\Models\Stock;
use App\Repositories\Contracts\StockRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class StockRepository implements StockRepositoryInterface
{
    public function trouverOuCreer(
        int     $locationId,
        string  $entiteType,
        int     $entiteId,
        ?int    $classementId = null
    ): Stock {
        return Stock::firstOrCreate(
            [
                'location_id'  => $locationId,
                'entite_type'  => $entiteType,
                'entite_id'    => $entiteId,
                'classement_id'=> $classementId,
            ],
            ['stock_total' => 0]
        );
    }

    public function incrementer(
        int     $locationId,
        string  $entiteType,
        int     $entiteId,
        float   $quantite,
        ?int    $classementId = null
    ): Stock {
        $stock = $this->trouverOuCreer(
            $locationId, $entiteType, $entiteId, $classementId
        );

        $stock->increment('stock_total', $quantite);

        return $stock->fresh();
    }

    public function decrementer(
        int     $locationId,
        string  $entiteType,
        int     $entiteId,
        float   $quantite,
        ?int    $classementId = null
    ): Stock {
        $stock = $this->trouverOuCreer(
            $locationId, $entiteType, $entiteId, $classementId
        );

        if ($stock->stock_total < $quantite) {
            throw new \DomainException(
                "Stock insuffisant. Disponible : {$stock->stock_total}, "
                . "demandé : {$quantite}"
            );
        }

        $stock->decrement('stock_total', $quantite);

        return $stock->fresh();
    }

    public function stockParLocation(int $locationId): Collection
    {
        return Stock::with(['classement.produit', 'location'])
                    ->where('location_id', $locationId)
                    ->where('stock_total', '>', 0)
                    ->get();
    }

    public function stockParEntite(
        string $entiteType,
        int    $entiteId
    ): Collection {
        return Stock::where('entite_type', $entiteType)
                    ->where('entite_id', $entiteId)
                    ->with('location')
                    ->get();
    }

    public function enRupture(): Collection
    {
        return Stock::where('stock_total', '<=', 0)->get();
    }
}
