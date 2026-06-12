<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
                'taux_horaire'    => 1_500.00,
                'salaire_mensuel' => 260_000.00,
            ],
            [
                'nom'             => 'Opérateur de broyage',
                'taux_horaire'    => 1_500.00,
                'salaire_mensuel' => 260_000.00,
            ],
            [
                'nom'             => 'Chef d\'équipe production',
                'taux_horaire'    => 2_200.00,
                'salaire_mensuel' => 380_000.00,
            ],
            [
                'nom'             => 'Technicien de maintenance',
                'taux_horaire'    => 2_500.00,
                'salaire_mensuel' => 430_000.00,
            ],
            [
                'nom'             => 'Responsable qualité',
                'taux_horaire'    => 3_000.00,
                'salaire_mensuel' => 520_000.00,
            ],
            [
                'nom'             => 'Manutentionnaire',
                'taux_horaire'    => 1_200.00,
                'salaire_mensuel' => 210_000.00,
            ],
            [
                'nom'             => 'Chauffeur livreur',
                'taux_horaire'    => 1_800.00,
                'salaire_mensuel' => 310_000.00,
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
