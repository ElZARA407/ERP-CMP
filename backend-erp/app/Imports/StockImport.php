<?php

namespace App\Imports;

use App\Models\ClassementProduit;
use App\Models\Location;
use App\Models\MatierePremiere;
use App\Models\Produit;
use App\Models\Stock;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

class StockImport implements WithMultipleSheets
{
    public function __construct(private readonly array $sheetNames = [])
    {
    }

    public function sheets(): array
    {
        $sheetNames = $this->normalizeSheetNames();
        $imports = [];

        foreach ($sheetNames as $sheetName) {
            $imports[$sheetName] = new StockSheetImport();
        }

        return $imports;
    }

    private function normalizeSheetNames(): array
    {
        $names = array_map(
            static fn ($name) => trim((string) $name),
            $this->sheetNames
        );

        $names = array_values(array_unique(array_filter($names, static fn (string $name) => $name !== '')));

        if ($names === []) {
            return ['stock'];
        }

        return $names;
    }
}

class StockSheetImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure
{
    private array $importErrors = [];

    public function model(array $row): ?Stock
    {
        $entiteType = strtolower(trim((string) ($row['entite_type'] ?? '')));
        if (!in_array($entiteType, ['matiere', 'produit'], true)) {
            throw new \RuntimeException("Type d'entité invalide pour l'import stock.");
        }

        $locationId = $this->resolveLocationId($row);
        $entiteId = $this->resolveEntiteId($row, $entiteType);
        $classementId = $this->resolveClassementId($row, $entiteType);
        $stockTotal = $this->nullableFloat(
            $row['stock_total']
            ?? $row['stocks_total']
            ?? $row['stocks-total']
            ?? $row['stock']
            ?? $row['quantite']
            ?? 0
        );

        if ($entiteType === 'produit' && $classementId === null) {
            throw new \RuntimeException('Classement requis pour un stock de produit.');
        }

        return Stock::updateOrCreate(
            [
                'location_id' => $locationId,
                'entite_type' => $entiteType,
                'entite_id' => $entiteId,
                'classement_id' => $classementId,
            ],
            [
                'stock_total' => $stockTotal ?? 0,
            ]
        );
    }

    private function resolveLocationId(array $row): int
    {
        $rawId = trim((string) ($row['location_id'] ?? ''));
        if ($rawId !== '' && is_numeric($rawId)) {
            $locationId = (int) $rawId;
            if (Location::whereKey($locationId)->exists()) {
                return $locationId;
            }
        }

        $label = $this->readLabel($row, ['location', 'location_nom', 'location_name']);
        if ($label !== null) {
            $location = Location::query()
                ->where('nom', $label)
                ->orWhere('nom', 'like', '%' . $label . '%')
                ->first();

            if ($location) {
                return (int) $location->id;
            }
        }

        throw new \RuntimeException('Location introuvable pour la ligne importée.');
    }

    private function resolveEntiteId(array $row, string $entiteType): int
    {
        $rawId = trim((string) ($row['entite_id'] ?? ''));
        if ($rawId !== '' && is_numeric($rawId)) {
            $entiteId = (int) $rawId;

            if ($entiteType === 'produit' && Produit::whereKey($entiteId)->exists()) {
                return $entiteId;
            }

            if ($entiteType === 'matiere' && MatierePremiere::whereKey($entiteId)->exists()) {
                return $entiteId;
            }
        }

        $label = $this->readLabel($row, ['entite', 'article', 'nom', 'designation', 'nomencla', 'reference']);
        if ($label === null) {
            throw new \RuntimeException('Impossible de résoudre l’article à importer.');
        }

        if ($entiteType === 'produit') {
            $produit = Produit::query()
                ->where('nomencla', $label)
                ->orWhere('designation', $label)
                ->orWhere('nomencla', 'like', '%' . $label . '%')
                ->orWhere('designation', 'like', '%' . $label . '%')
                ->first();

            if ($produit) {
                return (int) $produit->id;
            }
        }

        if ($entiteType === 'matiere') {
            $matiere = MatierePremiere::query()
                ->where('reference', $label)
                ->orWhere('nom', $label)
                ->orWhere('reference', 'like', '%' . $label . '%')
                ->orWhere('nom', 'like', '%' . $label . '%')
                ->first();

            if ($matiere) {
                return (int) $matiere->id;
            }
        }

        throw new \RuntimeException("Entité '{$label}' introuvable en base pour le type '{$entiteType}'.");
    }

    private function resolveClassementId(array $row, string $entiteType): ?int
    {
        if ($entiteType === 'matiere') {
            return null;
        }

        $rawId = trim((string) ($row['classement_id'] ?? ''));
        if ($rawId !== '' && is_numeric($rawId)) {
            $classementId = (int) $rawId;
            if (ClassementProduit::whereKey($classementId)->exists()) {
                return $classementId;
            }
        }

        $label = $this->readLabel($row, ['classement', 'qualite', 'classement_libelle', 'libelle']);
        if ($label === null) {
            return null;
        }

        $qualite = $this->normalizeQualite($label);
        if ($qualite === null) {
            throw new \RuntimeException("Classement '{$label}' invalide pour un stock produit.");
        }

        $classement = ClassementProduit::query()
            ->where('qualite', $qualite)
            ->first();

        if (!$classement) {
            throw new \RuntimeException("Classement '{$qualite}' introuvable en base.");
        }

        return (int) $classement->id;
    }

    private function readLabel(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $value = trim((string) $row[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function normalizeQualite(string $label): ?string
    {
        $normalized = strtolower(trim($label));
        $normalized = strtr($normalized, [
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'à' => 'a',
            'â' => 'a',
            'ù' => 'u',
            'û' => 'u',
            'ô' => 'o',
            'î' => 'i',
            'ï' => 'i',
            'ç' => 'c',
        ]);

        if (str_contains($normalized, '1er') || str_contains($normalized, '1ere') || str_contains($normalized, 'premier')) {
            return '1er';
        }

        if (str_contains($normalized, '2e') || str_contains($normalized, 'deux')) {
            return '2e';
        }

        if (str_contains($normalized, 'cass')) {
            return 'casse';
        }

        return in_array($normalized, ['1er', '2e', 'casse'], true) ? $normalized : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', (string) $value);
        return is_numeric($normalized) ? (float) $normalized : null;
    }

    public function getImportErrors(): array
    {
        return $this->importErrors;
    }

    public function rules(): array
    {
        return [
            'entite_type' => 'required|in:matiere,produit',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'entite_type' => 'entite_type',
        ];
    }

    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            $this->importErrors[] = [
                'ligne' => $failure->row(),
                'colonne' => $failure->attribute(),
                'erreurs' => $failure->errors(),
                'valeurs' => $failure->values(),
            ];

            Log::warning('Ligne ignorée (import stock)', [
                'ligne' => $failure->row(),
                'colonne' => $failure->attribute(),
                'erreurs' => $failure->errors(),
                'valeurs' => $failure->values(),
            ]);
        }
    }
}