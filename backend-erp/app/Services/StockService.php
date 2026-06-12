<?php
// app/Services/StockService.php

namespace App\Services;

use App\Enums\TypeMouvement;
use App\Models\MouvementStock;
use App\Models\Utilisateur;
use App\Repositories\Contracts\StockRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Service central de gestion des stocks.
 *
 * RÈGLE ABSOLUE :
 * Toute modification de stock passe par ce service.
 * Aucun code ne doit modifier stocks.stock_total directement.
 *
 * LARAVEL 13 / PHP 8.3 :
 * - Constructor property promotion avec readonly
 * - DB::transaction() pour l'atomicité
 */
class StockService
{
    public function __construct(
        private readonly StockRepositoryInterface $stockRepository
    ) {}

    // ── Entrée stock ────────────────────────────────────────
    public function entree(
        int        $locationId,
        string     $entiteType,
        int        $entiteId,
        float      $quantite,
        string     $referenceType,
        int        $referenceId,
        Utilisateur $operateur,
        ?int       $classementId = null
    ): void {
        DB::transaction(function () use (
            $locationId, $entiteType, $entiteId,
            $quantite, $referenceType, $referenceId,
            $operateur, $classementId
        ) {
            $this->stockRepository->incrementer(
                $locationId, $entiteType, $entiteId,
                $quantite, $classementId
            );

            $this->enregistrerMouvement(
                $locationId, $entiteType, $entiteId,
                TypeMouvement::ENTREE, $quantite,
                $referenceType, $referenceId,
                $operateur, $classementId
            );
        });
    }

    // ── Sortie stock ────────────────────────────────────────
    public function sortie(
        int        $locationId,
        string     $entiteType,
        int        $entiteId,
        float      $quantite,
        string     $referenceType,
        int        $referenceId,
        Utilisateur $operateur,
        ?int       $classementId = null
    ): void {
        DB::transaction(function () use (
            $locationId, $entiteType, $entiteId,
            $quantite, $referenceType, $referenceId,
            $operateur, $classementId
        ) {
            $this->stockRepository->decrementer(
                $locationId, $entiteType, $entiteId,
                $quantite, $classementId
            );

            $this->enregistrerMouvement(
                $locationId, $entiteType, $entiteId,
                TypeMouvement::SORTIE, $quantite,
                $referenceType, $referenceId,
                $operateur, $classementId
            );
        });
    }

    // ── Retour stock ────────────────────────────────────────
    public function retour(
        int        $locationId,
        string     $entiteType,
        int        $entiteId,
        float      $quantite,
        string     $referenceType,
        int        $referenceId,
        Utilisateur $operateur,
        ?int       $classementId = null
    ): void {
        DB::transaction(function () use (
            $locationId, $entiteType, $entiteId,
            $quantite, $referenceType, $referenceId,
            $operateur, $classementId
        ) {
            $this->stockRepository->incrementer(
                $locationId, $entiteType, $entiteId,
                $quantite, $classementId
            );

            $this->enregistrerMouvement(
                $locationId, $entiteType, $entiteId,
                TypeMouvement::RETOUR, $quantite,
                $referenceType, $referenceId,
                $operateur, $classementId
            );
        });
    }

    // ── Enregistrement mouvement ────────────────────────────
    private function enregistrerMouvement(
        int         $locationId,
        string      $entiteType,
        int         $entiteId,
        TypeMouvement $type,
        float       $quantite,
        string      $referenceType,
        int         $referenceId,
        Utilisateur  $operateur,
        ?int        $classementId = null
    ): void {
        MouvementStock::create([
            'location_id'    => $locationId,
            'entite_type'    => $entiteType,
            'entite_id'      => $entiteId,
            'classement_id'  => $classementId,
            'type'           => $type->value,
            'quantite'       => $quantite,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'utilisateur_id' => $operateur->id,
            'date_mouvement' => now(),
        ]);
    }
}
