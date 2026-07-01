<?php

namespace App\Imports;

use App\Models\MatierePremiere;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Validators\Failure;

class MatierePremiereImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure
{
    private array $categorieCache = [];

    
    private int $categorieFallbackCount = 0;

    public function __construct()
    {
    }

    /**
     * Transforme chaque ligne du fichier Excel en un modèle Produit.
     */
    public function model(array $row)
    {
        // dd($row);
        return new MatierePremiere([
            'reference'     => trim((string) ($row['reference'] ?? '')),
            'nom'  => trim((string) ($row['nom'] ?? '')),
            'type'   => $this->nullIfEmpty($row['type'] ?? null),
            'description'  => trim((string) ($row['description'] ?? '')),
            'unite'        => trim((string) ($row['unite'] ?? 'PCS')) ?: 'PCS',
            'prix_moyen'     => (float) ($row['prix_moyen'] ?? 0),
            'seuil'        => (float) ($row['seuil'] ?? 0),
            'actif'        => true,
        ]);
    }
    /**
     * Convertit une chaîne vide en null (utile pour contenance/format nullable).
     */
    private function nullIfEmpty(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * Règles de validation applicables aux lignes du fichier Excel.
     */
    public function rules(): array
    {
        return [
            'reference'    => 'required|string|max:30',
            'nom' => 'required|string|max:150',
        ];
    }

    /**
     * Personnalisation des noms d'attributs pour les messages d'erreur.
     */
    public function customValidationAttributes(): array
    {
        return [
            'reference'    => 'reference',
            'nom' => 'nom',
        ];
    }
        public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            Log::warning('Ligne ignorée (donnée matières incomplète)', [
                'ligne'    => $failure->row(),
                'colonne'  => $failure->attribute(),
                'erreurs'  => $failure->errors(),
                'valeurs'  => $failure->values(),
            ]);
        }
    }
}