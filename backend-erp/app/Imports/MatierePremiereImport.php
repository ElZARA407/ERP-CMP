<?php

namespace App\Imports;

use App\Models\MatierePremiere;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

class MatierePremiereImport implements WithMultipleSheets
{
    public function __construct(private readonly array $sheetNames = [])
    {
    }

    public function sheets(): array
    {
        $sheetNames = $this->normalizeSheetNames();
        $imports = [];

        foreach ($sheetNames as $sheetName) {
            $imports[$sheetName] = new MatierePremiereSheetImport();
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
            return ['matieres_premieres'];
        }

        return $names;
    }
}

class MatierePremiereSheetImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure
{
    public function __construct()
    {
    }

    public function model(array $row)
    {
        $reference = trim((string) ($row['reference'] ?? ''));
        $nom = trim((string) ($row['nom'] ?? ''));

        if ($reference === '' || $nom === '') {
            throw new \RuntimeException('Référence et nom sont obligatoires pour l’import matière première.');
        }

        return MatierePremiere::updateOrCreate(
            ['reference' => $reference],
            [
                'nom' => $nom,
                'type' => $this->nullIfEmpty($row['type'] ?? null),
                'description' => $this->nullIfEmpty($row['description'] ?? null),
                'unite' => trim((string) ($row['unite'] ?? 'PCS')) ?: 'PCS',
                'prix_moyen' => (float) ($row['prix_moyen'] ?? 0),
                'seuil' => (float) ($row['seuil'] ?? 0),
                'actif' => $this->toBool($row['actif'] ?? true),
            ]
        );
    }

    private function nullIfEmpty(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
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

    public function rules(): array
    {
        return [
            'reference' => 'required|string|max:30',
            'nom' => 'required|string|max:150',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'reference' => 'reference',
            'nom' => 'nom',
        ];
    }

    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            Log::warning('Ligne ignorée (donnée matières incomplète)', [
                'ligne' => $failure->row(),
                'colonne' => $failure->attribute(),
                'erreurs' => $failure->errors(),
                'valeurs' => $failure->values(),
            ]);
        }
    }
}