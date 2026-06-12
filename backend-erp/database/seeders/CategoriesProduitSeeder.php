<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategoriesProduitSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            CategorieProduit::INJ,
            CategorieProduit::HDPE,
            CategorieProduit::PET,
            CategorieProduit::MCH,
        ];

        foreach ($categories as $nom) {
            CategorieProduit::firstOrCreate(['nom' => $nom]);
        }

        $this->command->info('✅  Catégories produits créées : ' . count($categories));
    }
}
