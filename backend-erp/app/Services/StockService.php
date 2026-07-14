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
        ?int $classementId = null,
        ?string $motif = null
    ): void {
        DB::transaction(function () use (
            $locationId, $entiteType, $entiteId,
            $quantite, $referenceType, $referenceId,
            $operateur, $classementId, $motif
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
                classementId: $classementId,
                motif: $motif
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
        ?int $classementId = null,
        ?string $motif = null
    ): void {
        DB::transaction(function () use (
            $locationId, $entiteType, $entiteId,
            $quantite, $referenceType, $referenceId,
            $operateur, $classementId, $motif
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
                classementId: $classementId,
                motif: $motif
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
        ?int $classementId = null,
        ?string $motif = null
    ): void {
        DB::transaction(function () use (
            $locationId, $entiteType, $entiteId,
            $quantite, $referenceType, $referenceId,
            $operateur, $classementId, $motif
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
                classementId: $classementId,
                motif: $motif
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
            'motif' => $this->resolveMotif($motif, $referenceType, $type),
            'stock_theorique' => $stockTheorique,
            'stock_physique' => $stockPhysique,
            'ecart' => $ecart,
        ]);
    }

    private function resolveMotif(?string $motif, string $referenceType, TypeMouvement $type): string
    {
        $motif = trim((string) $motif);

        if ($motif !== '') {
            return $motif;
        }

        $reference = strtolower(trim($referenceType));

        $map = [
            'bp_session' => 'production',
            'production' => 'production',

            'journal_achat' => 'achat',
            'achat' => 'achat',
            'bon_reception' => 'réception BR',
            'reception_br' => 'réception BR',
            'br' => 'réception BR',

            'bt_session' => 'recyclage',
            'recyclage' => 'recyclage',
            'broyage' => 'recyclage',
            'transformation' => 'recyclage',

            'livraison' => 'livraison',
            'vente_directe' => 'vente directe',
            'bon_sortie' => 'bon de sortie',

            'ajustement_inventaire' => 'inventaire',
            'inventaire' => 'inventaire',

            'livraison_annulee' => 'annulation livraison',
            'vente_directe_annulee' => 'annulation vente directe',
        ];

        if (isset($map[$reference])) {
            return $map[$reference];
        }

        if ($type === TypeMouvement::INVENTAIRE) {
            return 'inventaire';
        }

        return ucfirst(str_replace('_', ' ', $reference));
    }
}