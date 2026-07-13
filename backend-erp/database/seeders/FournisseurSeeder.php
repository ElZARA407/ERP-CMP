<?php

namespace Database\Seeders;

use App\Models\Fournisseur;
use Illuminate\Database\Seeder;

class FournisseurSeeder extends Seeder
{
    public function run(): void
    {
        $fournisseurs = [
            [
                'nom' => 'SODIAT',
                'reference' => 'FRN-001',
                'NIF' => '5001234561',
                'STAT' => '100-200-300',
                'adresse' => 'Ankorondrano, Antananarivo',
                'email' => 'contact@sodiat.mg',
                'contact' => '+261 34 21 100 01',
                'interlocutaire' => 'Service commercial',
                'code_compta' => '401001',
                'actif' => true,
            ],
            [
                'nom' => 'JIRAMA Fourniture',
                'reference' => 'FRN-002',
                'NIF' => '5001234562',
                'STAT' => '100-200-301',
                'adresse' => 'Antaninarenina, Antananarivo',
                'email' => 'fourniture@jirama.mg',
                'contact' => '+261 34 21 100 02',
                'interlocutaire' => 'Approvisionnement',
                'code_compta' => '401002',
                'actif' => true,
            ],
            [
                'nom' => 'Mada Packaging',
                'reference' => 'FRN-003',
                'NIF' => '5001234563',
                'STAT' => '100-200-302',
                'adresse' => 'Tanjombato, Antananarivo',
                'email' => 'sales@madapackaging.mg',
                'contact' => '+261 34 21 100 03',
                'interlocutaire' => 'Ventes',
                'code_compta' => '401003',
                'actif' => true,
            ],
            [
                'nom' => 'Tech Maintenance',
                'reference' => 'FRN-004',
                'NIF' => '5001234564',
                'STAT' => '100-200-303',
                'adresse' => 'Andraharo, Antananarivo',
                'email' => 'contact@techmaintenance.mg',
                'contact' => '+261 34 21 100 04',
                'interlocutaire' => 'Maintenance',
                'code_compta' => '401004',
                'actif' => true,
            ],
            [
                'nom' => 'B2B Logistics',
                'reference' => 'FRN-005',
                'NIF' => '5001234565',
                'STAT' => '100-200-304',
                'adresse' => 'Zone forello, Tanjombato',
                'email' => 'contact@b2blogistics.mg',
                'contact' => '+261 34 21 100 05',
                'interlocutaire' => 'Logistique',
                'code_compta' => '401005',
                'actif' => true,
            ],
        ];

        foreach ($fournisseurs as $fournisseur) {
            Fournisseur::updateOrCreate(
                ['reference' => $fournisseur['reference']],
                $fournisseur
            );
        }

        $this->command?->info('Fournisseurs créés : ' . count($fournisseurs));
    }
}