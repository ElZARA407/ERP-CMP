<?php

namespace Database\Seeders;

use App\Models\Employe;
use App\Models\Poste;
use Illuminate\Database\Seeder;

class EmployeSeeder extends Seeder
{
    public function run(): void
    {
        $postes = Poste::query()->pluck('id', 'nom');

        $employes = [
            [
                'matricule' => 'EMP-001',
                'nom' => 'Rakotoarisoa',
                'prenom' => 'Jean',
                'poste' => 'Opérateur de production',
                'date_embauche' => now()->subYears(4)->toDateString(),
                'date_depart' => null,
                'actif' => true,
            ],
            [
                'matricule' => 'EMP-002',
                'nom' => 'Randriamamonjy',
                'prenom' => 'Marie',
                'poste' => 'Opérateur de broyage',
                'date_embauche' => now()->subYears(3)->toDateString(),
                'date_depart' => null,
                'actif' => true,
            ],
            [
                'matricule' => 'EMP-003',
                'nom' => 'Rasoanaivo',
                'prenom' => 'Paul',
                'poste' => 'Chef d\'équipe production',
                'date_embauche' => now()->subYears(5)->toDateString(),
                'date_depart' => null,
                'actif' => true,
            ],
            [
                'matricule' => 'EMP-004',
                'nom' => 'Andriamampionona',
                'prenom' => 'Lova',
                'poste' => 'Technicien de maintenance',
                'date_embauche' => now()->subYears(6)->toDateString(),
                'date_depart' => null,
                'actif' => true,
            ],
            [
                'matricule' => 'EMP-005',
                'nom' => 'Rakotondrazaka',
                'prenom' => 'Sarah',
                'poste' => 'Responsable qualité',
                'date_embauche' => now()->subYears(4)->toDateString(),
                'date_depart' => null,
                'actif' => true,
            ],
            [
                'matricule' => 'EMP-006',
                'nom' => 'Rabe',
                'prenom' => 'Toky',
                'poste' => 'Manutentionnaire',
                'date_embauche' => now()->subYears(2)->toDateString(),
                'date_depart' => null,
                'actif' => true,
            ],
            [
                'matricule' => 'EMP-007',
                'nom' => 'Rakotomalala',
                'prenom' => 'Faly',
                'poste' => 'Chauffeur livreur',
                'date_embauche' => now()->subYears(3)->toDateString(),
                'date_depart' => null,
                'actif' => true,
            ],
        ];

        foreach ($employes as $employe) {
            $posteId = $postes[$employe['poste']] ?? null;

            if (!$posteId) {
                throw new \RuntimeException(
                    "Poste introuvable pour l'employé {$employe['matricule']} : {$employe['poste']}"
                );
            }

            Employe::updateOrCreate(
                ['matricule' => $employe['matricule']],
                [
                    'nom' => $employe['nom'],
                    'prenom' => $employe['prenom'],
                    'poste_id' => $posteId,
                    'date_embauche' => $employe['date_embauche'],
                    'date_depart' => $employe['date_depart'],
                    'actif' => $employe['actif'],
                ]
            );
        }

        $this->command?->info('Employés créés : ' . count($employes));
    }
}