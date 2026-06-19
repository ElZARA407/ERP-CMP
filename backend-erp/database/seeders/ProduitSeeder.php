<?php

namespace Database\Seeders;

use App\Imports\ProduitImport;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Produit;
use App\Models\CategorieProduit;

class ProduitSeeder extends Seeder
{
    public function run(): void
    {
        // Garde-fou : les catégories doivent exister avant l'import,
        // sinon ProduitImport lèvera une RuntimeException.
        if (CategorieProduit::count() === 0) {
            $this->command->error("Aucune catégorie produit en base. Lancez d'abord CategoriesProduitSeeder.");
            $this->call(CategoriesProduitSeeder::class);
        }

        $filePath = database_path('seeders/data/produits_finis_classifies.xlsx');

        if (!file_exists($filePath)) {
            $this->command->error("Fichier non trouvé : {$filePath}");
            $this->command->info("Veuillez copier le fichier dans : database/seeders/data/");
            return;
        }

        $this->command->info('Début de l\'importation des produits...');

        try {
            $import = new ProduitImport();
            Excel::import($import, $filePath);

            $count = Produit::count();
            $this->command->info("Importation terminée avec succès !");
            $this->command->info("Nombre total de produits : {$count}");

            $fallbackCount = $import->getCategorieFallbackCount();
            if ($fallbackCount > 0) {
                $this->command->warn(
                    "⚠️  {$fallbackCount} ligne(s) ont une catégorie non reconnue et ont été classées en MCH par défaut. " .
                    "Vérifiez storage/logs/laravel.log pour le détail des lignes concernées."
                );
            }
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $this->command->error("Erreurs de validation pendant l'import :");
            foreach ($e->failures() as $failure) {
                $this->command->error(
                    "Ligne {$failure->row()} — colonne '{$failure->attribute()}' : " . implode(', ', $failure->errors())
                );
            }
        } catch (\Exception $e) {
            $this->command->error("Erreur pendant l'import : " . $e->getMessage());
        }
    }
}