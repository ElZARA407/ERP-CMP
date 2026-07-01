<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Location;
use App\Models\Utilisateur;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $roleAdmin      = Role::where('nom', Role::ADMIN)->firstOrFail();
        $roleCommercial = Role::where('nom', Role::COMMERCIAL)->firstOrFail();
        $siege          = Location::where('type', Location::TYPE_BUREAU)->firstOrFail();

        // ── Compte Administrateur ──────────────────────────
        Utilisateur::firstOrCreate(
            ['email' => 'admin@cmp.mg'],
            [
                'nom'         => 'Administrateur CMP',
                'password'    => Hash::make('projet12'),
                'role_id'     => $roleAdmin->id,
                'location_id' => $siege->id,
                'actif'       => true,
            ]
        );

        // ── Compte Commercial démo ─────────────────────────
        Utilisateur::firstOrCreate(
            ['email' => 'commercial@cmp.mg'],
            [
                'nom'         => 'Commercial Démo',
                'password'    => Hash::make('projet12'),
                'role_id'     => $roleCommercial->id,
                'location_id' => $siege->id,
                'actif'       => true,
            ]
        );

        $this->command->info('✅  Comptes admin et démo créés.');
        $this->command->warn(
            '⚠️   Changez les mots de passe avant la mise en production !'
        );
    }
}

