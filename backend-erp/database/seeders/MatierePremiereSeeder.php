<?php

namespace Database\Seeders;

use App\Imports\MatierePremiereImport;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\MatierePremiere;

class MatierePremiereSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = database_path('seeders/data/matieres_premieres.xlsx');

        if (!file_exists($filePath)) {
            $this->command->error("Fichier non trouvé : {$filePath}");
            $this->command->info("Veuillez copier le fichier dans : database/seeders/data/");
            return;
        }

        $this->command->info('Début de l\'importation des matières premières...');

        try {
            $import = new MatierePremiereImport();
            Excel::import($import, $filePath);

            $count = MatierePremiere::count();
            $this->command->info("Importation terminée avec succès !");
            $this->command->info("Nombre total de matières premières : {$count}");


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
