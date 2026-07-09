<?php

namespace App\Imports;

use App\Models\CategorieProduit;
use App\Models\Produit;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

class ProduitImport implements WithMultipleSheets
{
    public function __construct(private readonly array $sheetNames = [])
    {
    }

    public function sheets(): array
    {
        $sheetNames = $this->normalizeSheetNames();
        $imports = [];

        foreach ($sheetNames as $sheetName) {
            $imports[$sheetName] = new ProduitSheetImport();
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
            return ['produits_finis_classifies'];
        }

        return $names;
    }
}

class ProduitSheetImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure
{
    /**
     * Cache local des catégories pour éviter une requête SQL par ligne.
     * Clé = code normalisé (PET, HDPE, INJ, MCH), valeur = id.
     */
    private array $categorieCache = [];

    /**
     * Compteur de lignes tombées sur la catégorie par défaut (MCH),
     * pour signaler une éventuelle erreur de mapping après l'import.
     */
    private int $categorieFallbackCount = 0;

    public function __construct()
    {
        $this->categorieCache = CategorieProduit::pluck('id', 'nom')->all();
    }

    public function model(array $row)
    {
        $nomencla = trim((string) ($row['nomencla'] ?? ''));
        $designation = trim((string) ($row['designation'] ?? ''));

        if ($nomencla === '' || $designation === '') {
            throw new \RuntimeException('Nomencla et désignation sont obligatoires pour l’import produit.');
        }

        $categorieId = $this->resolveCategorieId($row);

        return Produit::updateOrCreate(
            ['nomencla' => $nomencla],
            [
                'designation' => $designation,
                'categorie_id' => $categorieId,
                'contenance' => $this->nullIfEmpty($row['contenance'] ?? null),
                'format' => $this->nullIfEmpty($row['format'] ?? null),
                'unite' => trim((string) ($row['unite'] ?? 'pc')) ?: 'pc',
                'colisage' => (float) ($row['colisage'] ?? 1),
                'poids' => trim((string) ($row['poids'] ?? '0')) ?: '0',
                'seuil' => $this->nullableFloat($row['seuil'] ?? null),
                'actif' => $this->toBool($row['actif'] ?? true),
            ]
        );
    }

    private function resolveCategorieId(array $row): int
    {
        $raw = trim((string) (
            $row['categorie_id']
            ?? $row['categorie']
            ?? $row['categorie_nom']
            ?? ''
        ));

        if ($raw !== '' && is_numeric($raw)) {
            $categorieId = (int) $raw;

            if (CategorieProduit::whereKey($categorieId)->exists()) {
                return $categorieId;
            }
        }

        $codeClean = strtoupper($raw);
        $codesValides = [
            CategorieProduit::PET,
            CategorieProduit::HDPE,
            CategorieProduit::INJ,
            CategorieProduit::MCH,
        ];

        $codeRecherche = in_array($codeClean, $codesValides, true)
            ? $codeClean
            : CategorieProduit::MCH;

        if ($codeRecherche === CategorieProduit::MCH && $codeClean !== CategorieProduit::MCH) {
            $this->categorieFallbackCount++;
            Log::warning("ProduitImport: code catégorie '{$raw}' non reconnu, classé en MCH par défaut.");
        }

        if (!isset($this->categorieCache[$codeRecherche])) {
            throw new \RuntimeException(
                "Catégorie '{$codeRecherche}' introuvable en base. Lancez d'abord le seeder des catégories."
            );
        }

        return (int) $this->categorieCache[$codeRecherche];
    }

    private function nullIfEmpty(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', (string) $value);
        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) ((int) $value);
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'oui', 'on'], true);
    }

    public function getCategorieFallbackCount(): int
    {
        return $this->categorieFallbackCount;
    }

    public function rules(): array
    {
        return [
            'nomencla' => 'required|string|max:30',
            'designation' => 'required|string|max:150',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'nomencla' => 'Nomencla',
            'designation' => 'Désignation',
        ];
    }

    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            Log::warning('Ligne ignorée (donnée produit incomplète)', [
                'ligne' => $failure->row(),
                'colonne' => $failure->attribute(),
                'erreurs' => $failure->errors(),
                'valeurs' => $failure->values(),
            ]);
        }
    }
}