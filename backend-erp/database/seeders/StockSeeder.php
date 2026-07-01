<?php

namespace Database\Seeders;

use App\Imports\StockImport;
use App\Models\Stock;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class StockSeeder extends Seeder
{
    public function run(): void
    {
        $filePath = database_path('seeders/data/stocks.xlsx');

        if (!file_exists($filePath)) {
            $this->command->error("Fichier non trouvé : {$filePath}");
            $this->command->info("Veuillez copier le fichier dans : database/seeders/data/");
            return;
        }

        $this->command->info('Début de l\'importation des stocks...');

        try {
            $import = new StockImport();
            Excel::import($import, $filePath);

            // Afficher les erreurs métier (entités introuvables)
            // StockSeeder.php — ligne à changer
            foreach ($import->getImportErrors() as $error) {  // getErrors() → getImportErrors()
                $this->command->warn($error);
            }

            $count = Stock::count();
            $this->command->info("Importation terminée !");
            $this->command->info("Nombre total de stocks : {$count}");

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $this->command->error("Erreurs de validation :");
            foreach ($e->failures() as $failure) {
                $this->command->error(
                    "Ligne {$failure->row()} — '{$failure->attribute()}' : "
                    . implode(', ', $failure->errors())
                );
            }
        } catch (\Exception $e) {
            $this->command->error("Erreur : " . $e->getMessage());
        }
    }
}