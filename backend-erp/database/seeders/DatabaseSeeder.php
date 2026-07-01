<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Vague 0 — Aucune dépendance
            RolesSeeder::class,
            LocationsSeeder::class,
            PostesSeeder::class,
            CategoriesProduitSeeder::class,

            // Vague 1 — Dépend de Vague 0
            AdminSeeder::class,
            ProduitSeeder::class,
            MatierePremiereSeeder::class,
            ClassementProduitSeeder::class,
            StockSeeder::class,

            // Optionnel en dev/staging
            // DemoDataSeeder::class,
        ]);
    }
}
