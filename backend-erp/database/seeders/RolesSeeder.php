<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'nom'         => Role::ADMIN,
                'description' => 'Accès complet à tous les modules ERP. '
                    . 'Gestion des utilisateurs, paramétrage système, '
                    . 'consultation de tous les KPI.',
            ],
            [
                'nom'         => Role::RESPONSABLE_PROD,
                'description' => 'Gestion complète de la production et du recyclage. '
                    . 'Création et validation des bons de production et transformation. '
                    . 'Consultation des stocks matières.',
            ],
            [
                'nom'         => Role::OPERATEUR_SAISIE,
                'description' => 'Saisie des sessions de production et recyclage. '
                    . 'Enregistrement des événements (pannes, pauses). '
                    . 'Consultation uniquement des autres modules.',
            ],
            [
                'nom'         => Role::COMMERCIAL,
                'description' => 'Gestion complète du cycle commercial : '
                    . 'clients, commandes, contrats, ventes directes. '
                    . 'Consultation des stocks produits finis.',
            ],
            [
                'nom'         => Role::LOGISTIQUE,
                'description' => 'Gestion des livraisons et bons de sortie. '
                    . 'Consultation des commandes et stocks.',
            ],
            [
                'nom'         => Role::FINANCE,
                'description' => 'Gestion de la facturation et suivi des paiements. '
                    . 'Accès aux rapports financiers et KPI.',
            ],
            [
                'nom'         => Role::RESPONSABLE_ACHAT,
                'description' => 'Gestion des demandes d\'achat et bons de réception. '
                    . 'Gestion du référentiel fournisseurs.',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['nom' => $role['nom']],
                ['description' => $role['description']]
            );
        }

        $this->command->info('✅  Rôles créés : ' . count($roles));
    }
}
