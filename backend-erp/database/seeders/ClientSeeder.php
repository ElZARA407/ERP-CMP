<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $clients = [
            [
                'nom' => 'SHOPRITE Madagascar',
                'reference' => 'CLT-001',
                'NIF' => '4001234567',
                'STAT' => '000-123-456',
                'adresse' => 'Ankorondrano, Antananarivo',
                'email' => 'contact@shoprite.mg',
                'contact' => '+261 34 11 000 01',
                'interlocutaire' => 'Service achats',
                'code_compta' => '411001',
                'facturation' => '30',
                'actif' => true,
            ],
            [
                'nom' => 'STAR Madagascar',
                'reference' => 'CLT-002',
                'NIF' => '4001234568',
                'STAT' => '000-123-457',
                'adresse' => 'Andraharo, Antananarivo',
                'email' => 'commercial@star.mg',
                'contact' => '+261 34 11 000 02',
                'interlocutaire' => 'Direction commerciale',
                'code_compta' => '411002',
                'facturation' => '30',
                'actif' => true,
            ],
            [
                'nom' => 'SOCOLAIT',
                'reference' => 'CLT-003',
                'NIF' => '4001234569',
                'STAT' => '000-123-458',
                'adresse' => 'Zone industrielle Forello, Tanjombato',
                'email' => 'achat@socolait.mg',
                'contact' => '+261 34 11 000 03',
                'interlocutaire' => 'Achats',
                'code_compta' => '411003',
                'facturation' => '45',
                'actif' => true,
            ],
            [
                'nom' => 'JIRAMA',
                'reference' => 'CLT-004',
                'NIF' => '4001234570',
                'STAT' => '000-123-459',
                'adresse' => 'Antaninarenina, Antananarivo',
                'email' => 'contact@jirama.mg',
                'contact' => '+261 34 11 000 04',
                'interlocutaire' => 'Approvisionnement',
                'code_compta' => '411004',
                'facturation' => '30',
                'actif' => true,
            ],
            [
                'nom' => 'Mika Distribution',
                'reference' => 'CLT-005',
                'NIF' => '4001234571',
                'STAT' => '000-123-460',
                'adresse' => 'Tana Water Front, Ambodivona',
                'email' => 'contact@mikadistribution.mg',
                'contact' => '+261 34 11 000 05',
                'interlocutaire' => 'Service client',
                'code_compta' => '411005',
                'facturation' => '15',
                'actif' => true,
            ],
        ];

        foreach ($clients as $client) {
            Client::updateOrCreate(
                ['reference' => $client['reference']],
                $client
            );
        }

        $this->command?->info('Clients créés : ' . count($clients));
    }
}