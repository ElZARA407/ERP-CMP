<?php

namespace App\Services;

use App\Enums\TypeMouvement;
use App\Models\MouvementStock;
use App\Models\Utilisateur;
use App\Repositories\Contracts\StockRepositoryInterface;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function __construct(
        private readonly StockRepositoryInterface $stockRepository
    ) {}

    public function entree(
        int $locationId,
        string $entiteType,
        int $entiteId,
        float $quantite,
        string $referenceType,
        int $referenceId,
        Utilisateur $operateur,
        ?int $classementId = null
    ): void {
        DB::transaction(function () use (
            $locationId, $entiteType, $entiteId,
            $quantite, $referenceType, $referenceId,
            $operateur, $classementId
        ) {
            $this->stockRepository->incrementer(
                $locationId,
                $entiteType,
                $entiteId,
                $quantite,
                $classementId
            );

            $this->enregistrerMouvement(
                locationId: $locationId,
                entiteType: $entiteType,
                entiteId: $entiteId,
                type: TypeMouvement::ENTREE,
                quantite: $quantite,
                referenceType: $referenceType,
                referenceId: $referenceId,
                operateur: $operateur,
                classementId: $classementId
            );
        });
    }

    public function sortie(
        int $locationId,
        string $entiteType,
        int $entiteId,
        float $quantite,
        string $referenceType,
        int $referenceId,
        Utilisateur $operateur,
        ?int $classementId = null
    ): void {
        DB::transaction(function () use (
            $locationId, $entiteType, $entiteId,
            $quantite, $referenceType, $referenceId,
            $operateur, $classementId
        ) {
            $this->stockRepository->decrementer(
                $locationId,
                $entiteType,
                $entiteId,
                $quantite,
                $classementId
            );

            $this->enregistrerMouvement(
                locationId: $locationId,
                entiteType: $entiteType,
                entiteId: $entiteId,
                type: TypeMouvement::SORTIE,
                quantite: $quantite,
                referenceType: $referenceType,
                referenceId: $referenceId,
                operateur: $operateur,
                classementId: $classementId
            );
        });
    }

    public function retour(
        int $locationId,
        string $entiteType,
        int $entiteId,
        float $quantite,
        string $referenceType,
        int $referenceId,
        Utilisateur $operateur,
        ?int $classementId = null
    ): void {
        DB::transaction(function () use (
            $locationId, $entiteType, $entiteId,
            $quantite, $referenceType, $referenceId,
            $operateur, $classementId
        ) {
            $this->stockRepository->incrementer(
                $locationId,
                $entiteType,
                $entiteId,
                $quantite,
                $classementId
            );

            $this->enregistrerMouvement(
                locationId: $locationId,
                entiteType: $entiteType,
                entiteId: $entiteId,
                type: TypeMouvement::RETOUR,
                quantite: $quantite,
                referenceType: $referenceType,
                referenceId: $referenceId,
                operateur: $operateur,
                classementId: $classementId
            );
        });
    }

    public function ajusterInventaire(
        int $locationId,
        string $entiteType,
        int $entiteId,
        float $stockPhysique,
        string $motif,
        Utilisateur $operateur,
        ?int $classementId = null
    ): MouvementStock {
        return DB::transaction(function () use (
            $locationId,
            $entiteType,
            $entiteId,
            $stockPhysique,
            $motif,
            $operateur,
            $classementId
        ) {
            $stock = $this->stockRepository->trouverOuCreer(
                $locationId,
                $entiteType,
                $entiteId,
                $classementId
            );

            $stockTheorique = (float) $stock->stock_total;
            $ecart = round($stockPhysique - $stockTheorique, 3);

            if ($ecart == 0.0) {
                throw new \DomainException('Aucun ecart detecte pour cet inventaire.');
            }

            $this->stockRepository->ajusterVers(
                $locationId,
                $entiteType,
                $entiteId,
                $stockPhysique,
                $classementId
            );

            return $this->enregistrerMouvement(
                locationId: $locationId,
                entiteType: $entiteType,
                entiteId: $entiteId,
                type: TypeMouvement::INVENTAIRE,
                quantite: abs($ecart),
                referenceType: 'ajustement_inventaire',
                referenceId: $stock->id,
                operateur: $operateur,
                classementId: $classementId,
                motif: $motif,
                stockTheorique: $stockTheorique,
                stockPhysique: $stockPhysique,
                ecart: $ecart
            );
        });
    }

    private function enregistrerMouvement(
        int $locationId,
        string $entiteType,
        int $entiteId,
        TypeMouvement $type,
        float $quantite,
        string $referenceType,
        int $referenceId,
        Utilisateur $operateur,
        ?int $classementId = null,
        ?string $motif = null,
        ?float $stockTheorique = null,
        ?float $stockPhysique = null,
        ?float $ecart = null
    ): MouvementStock {
        return MouvementStock::create([
            'location_id' => $locationId,
            'entite_type' => $entiteType,
            'entite_id' => $entiteId,
            'classement_id' => $classementId,
            'type' => $type->value,
            'quantite' => $quantite,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'utilisateur_id' => $operateur->id,
            'date_mouvement' => now(),
            'motif' => $motif,
            'stock_theorique' => $stockTheorique,
            'stock_physique' => $stockPhysique,
            'ecart' => $ecart,
        ]);
    }
}