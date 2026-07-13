<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Role;
use App\Models\Utilisateur;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UtilisateurSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::query()->pluck('id', 'nom');

        $bureau = Location::query()
            ->where('type', Location::TYPE_BUREAU)
            ->firstOrFail();

        $usine = Location::query()
            ->where('type', Location::TYPE_USINE)
            ->firstOrFail();

        $password = Hash::make('projet12');

        $users = [
            [
                'nom' => 'Administrateur CMP',
                'email' => 'admin@cmp.mg',
                'role' => Role::ADMIN,
                'location_id' => $bureau->id,
            ],
            [
                'nom' => 'Responsable Production',
                'email' => 'prod@cmp.mg',
                'role' => Role::RESPONSABLE_PROD,
                'location_id' => $usine->id,
            ],
            [
                'nom' => 'Opérateur Saisie',
                'email' => 'saisie@cmp.mg',
                'role' => Role::OPERATEUR_SAISIE,
                'location_id' => $usine->id,
            ],
            [
                'nom' => 'Commercial Démo',
                'email' => 'commercial@cmp.mg',
                'role' => Role::COMMERCIAL,
                'location_id' => $bureau->id,
            ],
            [
                'nom' => 'Logistique Démo',
                'email' => 'logistique@cmp.mg',
                'role' => Role::LOGISTIQUE,
                'location_id' => $usine->id,
            ],
            [
                'nom' => 'Finance Démo',
                'email' => 'finance@cmp.mg',
                'role' => Role::FINANCE,
                'location_id' => $bureau->id,
            ],
            [
                'nom' => 'Responsable Achat',
                'email' => 'achat@cmp.mg',
                'role' => Role::RESPONSABLE_ACHAT,
                'location_id' => $usine->id,
            ],
        ];

        foreach ($users as $user) {
            $roleId = $roles[$user['role']] ?? null;

            if (!$roleId) {
                throw new \RuntimeException(
                    "Rôle introuvable pour l'utilisateur {$user['email']} : {$user['role']}"
                );
            }

            Utilisateur::updateOrCreate(
                ['email' => $user['email']],
                [
                    'nom' => $user['nom'],
                    'password' => $password,
                    'role_id' => $roleId,
                    'location_id' => $user['location_id'],
                    'actif' => true,
                ]
            );
        }

        $this->command?->info('Utilisateurs créés : ' . count($users));
        $this->command?->warn('Mot de passe de démo : projet12');
    }
}