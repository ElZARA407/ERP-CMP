<?php

namespace Database\Seeders;

use App\Models\Machine;
use Illuminate\Database\Seeder;

class MachinesSeeder extends Seeder
{
    public function run(): void
    {
        $machines = [
            [
                'nom' => 'Ligne d\'extrusion 01',
                'description' => 'Ligne principale de production des articles plastiques.',
                'actif' => true,
            ],
            [
                'nom' => 'Broyeur principal',
                'description' => 'Machine de broyage des rebuts et matières recyclables.',
                'actif' => true,
            ],
            [
                'nom' => 'Presse injection 01',
                'description' => 'Presse dédiée aux produits techniques.',
                'actif' => true,
            ],
            [
                'nom' => 'Souffleuse PET 01',
                'description' => 'Souffleuse pour la fabrication de produits PET.',
                'actif' => true,
            ],
            [
                'nom' => 'Compresseur atelier',
                'description' => 'Compresseur de support pour les opérations de production.',
                'actif' => true,
            ],
        ];

        foreach ($machines as $machine) {
            Machine::updateOrCreate(
                ['nom' => $machine['nom']],
                [
                    'description' => $machine['description'],
                    'actif' => $machine['actif'],
                ]
            );
        }

        $this->command?->info('Machines créées : ' . count($machines));
    }
}