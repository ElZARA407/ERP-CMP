<?php

namespace App\Imports;

use App\Models\Stock;
use App\Models\MatierePremiere;
use App\Models\Produit;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StockImport implements ToModel, WithHeadingRow
{
    private array $importErrors = [];

    public function model(array $row): ?Stock
    {
        // Ignorer les lignes vides
        if (empty($row['nom']) && empty($row['entite_id'])) {
            return null;
        }

        $entiteType = $row['entite_type'] ?? null;
        $nom        = trim($row['nom'] ?? '');

        if (!in_array($entiteType, ['matiere', 'produit'])) {
            $this->importErrors[] = "Type inconnu '{$entiteType}' pour : {$nom}";
            return null;
        }

        $entite = match ($entiteType) {
            'matiere' => MatierePremiere::where('designation', $nom)->first(),
            'produit' => Produit::where('designation', $nom)->first(),
        };

        if (!$entite) {
            $this->importErrors[] = "Introuvable en BDD : type={$entiteType}, nom=\"{$nom}\"";
            return null;
        }

        $classementId = ($entiteType === 'produit') ? ($row['classement_id'] ?? null) : null;
        $stockTotal   = $row['stocks_total'] ?? $row['stocks-total'] ?? 0;

        // updateOrCreate évite le doublon si le seeder est relancé
        Stock::updateOrCreate(
            [
                'location_id'   => $row['location_id'],
                'entite_type'   => $entiteType,
                'entite_id'     => $entite->id,
                'classement_id' => $classementId,
            ],
            [
                'stock_total' => $stockTotal,
            ]
        );

        // Retourner null car on gère l'insertion manuellement
        return null;
    }

    public function getImportErrors(): array
    {
        return $this->importErrors;
    }
}