<?php

namespace Database\Seeders;

use App\Models\ClassementProduit;
use Illuminate\Database\Seeder;

class ClassementProduitSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Création des classements de qualité...');

        $classements = [
            ['qualite' => '1er',   'libelle' => 'Q1', 'actif' => true],
            ['qualite' => '2e',    'libelle' => 'Q2', 'actif' => true],
            ['qualite' => 'casse', 'libelle' => 'Cassé',        'actif' => true],
        ];

        foreach ($classements as $data) {
            ClassementProduit::firstOrCreate(
                ['qualite' => $data['qualite']],
                $data
            );
        }

        $this->command->info('Classements créés : 1er, 2e, casse.');
    }
}