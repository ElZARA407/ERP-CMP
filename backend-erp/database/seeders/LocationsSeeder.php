<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LocationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            [
                'nom'  => 'Siège Antananarivo',
                'type' => Location::TYPE_BUREAU,
            ],
            [
                'nom'  => 'Usine Andraharo',
                'type' => Location::TYPE_USINE,
            ],
            [
                'nom'  => 'Usine Tanjombato',
                'type' => Location::TYPE_USINE,
            ],
        ];

        foreach ($locations as $location) {
            Location::firstOrCreate(
                ['nom' => $location['nom']],
                ['type' => $location['type']]
            );
        }

        $this->command->info('✅  Sites créés : ' . count($locations));
    }
}
