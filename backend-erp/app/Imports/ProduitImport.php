<?php

namespace App\Imports;

use App\Models\CategorieProduit;
use App\Models\Produit;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Validators\Failure;

class ProduitImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure
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
        // Maatwebsite normalise déjà les en-têtes (WithHeadingRow) en snake_case
        // minuscule, sans accents : "Categorie-id" -> "categorie_id", "Designation" -> "designation", etc.
        // On précharge donc directement par le nom de colonne réel en base ('nom').
        $this->categorieCache = CategorieProduit::pluck('id', 'nom')->all();
    }

    /**
     * Transforme chaque ligne du fichier Excel en un modèle Produit.
     */
    public function model(array $row)
    {
        // dd($row);
        return new Produit([
            'nomencla'     => trim((string) ($row['nomencla'] ?? '')),
            'designation'  => trim((string) ($row['designation'] ?? '')),
            'categorie_id' => $this->resolveCategorieId($row['categorie_id'] ?? ''),
            'contenance'   => $this->nullIfEmpty($row['contenance'] ?? null),
            'format'       => $this->nullIfEmpty($row['format'] ?? null),
            'unite'        => trim((string) ($row['unite'] ?? 'PCS')) ?: 'PCS',
            'colisage'     => (float) ($row['colisage'] ?? 0),
            'poids'        => trim((string) ($row['poids'] ?? '0')),
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
     * Récupère l'identifiant numérique de la catégorie à partir de son code Excel.
     * Retombe sur MCH si le code est inconnu, et trace l'incident.
     */
    private function resolveCategorieId(string $code): int
    {
        $codeClean = strtoupper(trim($code));

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
            Log::warning("ProduitImport: code catégorie '{$code}' non reconnu, classé en MCH par défaut.");
        }

        if (!isset($this->categorieCache[$codeRecherche])) {
            // Sécurité ultime : si même MCH n'existe pas en base, on lève une erreur claire
            // plutôt que d'insérer un categorie_id=1 potentiellement faux.
            throw new \RuntimeException(
                "Catégorie '{$codeRecherche}' introuvable en base. Lancez d'abord CategoriesProduitSeeder."
            );
        }

        return (int) $this->categorieCache[$codeRecherche];
    }

    /**
     * Nombre de lignes importées avec une catégorie inconnue (fallback MCH).
     * Utilisé par le Seeder pour afficher un avertissement final.
     */
    public function getCategorieFallbackCount(): int
    {
        return $this->categorieFallbackCount;
    }

    /**
     * Règles de validation applicables aux lignes du fichier Excel.
     */
    public function rules(): array
    {
        return [
            'nomencla'    => 'required|string|max:30',
            'designation' => 'required|string|max:150',
        ];
    }

    /**
     * Personnalisation des noms d'attributs pour les messages d'erreur.
     */
    public function customValidationAttributes(): array
    {
        return [
            'nomencla'    => 'Nomencla',
            'designation' => 'Désignation',
        ];
    }
        public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            Log::warning('Ligne ignorée (donnée produit incomplète)', [
                'ligne'    => $failure->row(),
                'colonne'  => $failure->attribute(),
                'erreurs'  => $failure->errors(),
                'valeurs'  => $failure->values(),
            ]);
        }
    }
}