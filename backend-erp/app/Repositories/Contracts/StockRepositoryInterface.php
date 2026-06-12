<?php
// app/Repositories/Contracts/StockRepositoryInterface.php

namespace App\Repositories\Contracts;

use App\Models\Stock;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface du Repository Stock.
 * Sépare la logique de requête de la logique métier (Services).
 *
 * LARAVEL 13 / PHP 8.3 :
 * - Typage strict sur tous les paramètres et retours
 * - readonly properties dans les implémentations
 */
interface StockRepositoryInterface
{
    public function trouverOuCreer(
        int     $locationId,
        string  $entiteType,
        int     $entiteId,
        ?int    $classementId = null
    ): Stock;

    public function incrementer(
        int     $locationId,
        string  $entiteType,
        int     $entiteId,
        float   $quantite,
        ?int    $classementId = null
    ): Stock;

    public function decrementer(
        int     $locationId,
        string  $entiteType,
        int     $entiteId,
        float   $quantite,
        ?int    $classementId = null
    ): Stock;

    public function stockParLocation(int $locationId): Collection;

    public function stockParEntite(
        string $entiteType,
        int    $entiteId
    ): Collection;

    public function enRupture(): Collection;
}
