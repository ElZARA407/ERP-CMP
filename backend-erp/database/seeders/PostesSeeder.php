<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Poste;

class PostesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $postes = [
            [
                'nom'             => 'Opérateur de production',
                'taux_horaire'    => 500.00,
                'salaire_mensuel' => 260000.00,
            ],
            [
                'nom'             => 'Opérateur de broyage',
                'taux_horaire'    => 500.00,
                'salaire_mensuel' => 260000.00,
            ],
            [
                'nom'             => 'Chef d\'équipe production',
                'taux_horaire'    => 200.00,
                'salaire_mensuel' => 380000.00,
            ],
            [
                'nom'             => 'Technicien de maintenance',
                'taux_horaire'    => 2500.00,
                'salaire_mensuel' => 430000.00,
            ],
            [
                'nom'             => 'Responsable qualité',
                'taux_horaire'    => 3000.00,
                'salaire_mensuel' => 520000.00,
            ],
            [
                'nom'             => 'Manutentionnaire',
                'taux_horaire'    => 1200.00,
                'salaire_mensuel' => 210000.00,
            ],
            [
                'nom'             => 'Chauffeur livreur',
                'taux_horaire'    => 1800.00,
                'salaire_mensuel' => 310000.00,
            ],
        ];

        foreach ($postes as $poste) {
            Poste::firstOrCreate(
                ['nom' => $poste['nom']],
                [
                    'taux_horaire'    => $poste['taux_horaire'],
                    'salaire_mensuel' => $poste['salaire_mensuel'],
                ]
            );
        }

        $this->command->info('✅  Postes créés : ' . count($postes));
    }

}
