<?php

namespace App\Repositories\Contracts;

use App\Models\Stock;
use Illuminate\Database\Eloquent\Collection;

interface StockRepositoryInterface
{
    public function trouverOuCreer(
        int $locationId,
        string $entiteType,
        int $entiteId,
        ?int $classementId = null
    ): Stock;

    public function incrementer(
        int $locationId,
        string $entiteType,
        int $entiteId,
        float $quantite,
        ?int $classementId = null
    ): Stock;

    public function decrementer(
        int $locationId,
        string $entiteType,
        int $entiteId,
        float $quantite,
        ?int $classementId = null
    ): Stock;

    public function ajusterVers(
        int $locationId,
        string $entiteType,
        int $entiteId,
        float $stockPhysique,
        ?int $classementId = null
    ): Stock;

    public function stockParLocation(int $locationId): Collection;

    public function stockParEntite(
        string $entiteType,
        int $entiteId
    ): Collection;

    public function enRupture(): Collection;
}