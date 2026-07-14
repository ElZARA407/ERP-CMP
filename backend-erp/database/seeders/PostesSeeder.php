<?php

namespace Database\Seeders;

use App\Models\Poste;
use Illuminate\Database\Seeder;

class PostesSeeder extends Seeder
{
    public function run(): void
    {
        $postes = [
            ['nom' => 'Opérateur de production', 'salaire_mensuel' => 500000],
            ['nom' => 'Opérateur de broyage', 'salaire_mensuel' => 500000],
            ['nom' => 'Chef d\'équipe production', 'salaire_mensuel' => 800000],
            ['nom' => 'Technicien de maintenance', 'salaire_mensuel' => 450000],
            ['nom' => 'Responsable qualité', 'salaire_mensuel' => 500000],
            ['nom' => 'Manutentionnaire', 'salaire_mensuel' => 400000],
            ['nom' => 'Chauffeur livreur', 'salaire_mensuel' => 350000],
        ];

        foreach ($postes as $poste) {
            $tauxHoraire = round($poste['salaire_mensuel'] / 173.33, 2);

            Poste::updateOrCreate(
                ['nom' => $poste['nom']],
                [
                    'salaire_mensuel' => $poste['salaire_mensuel'],
                    'taux_horaire' => $tauxHoraire,
                ]
            );
        }
        $this->command->info('✅  Postes créés : ' . count($postes));
    }
}
