<?php
// database/seeders/DemoDataSeeder.php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Fournisseur;
use App\Models\MatierePremiere;
use App\Models\Produit;
use App\Models\ClassementProduit;
use App\Models\CategorieProduit;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration — NE PAS exécuter en production.
 * Activer dans DatabaseSeeder::run() uniquement pour dev/staging.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->creerClients();
        $this->creerFournisseurs();
        $this->creerMatieresPremieres();
        $this->creerProduits();

        $this->command->info('✅  Données de démo créées.');
    }

    // ──────────────────────────────────────────────────────
    private function creerClients(): void
    {
        $clients = [
            [
                'nom'       => 'SHOPRITE Madagascar',
                'reference' => 'CLT-001',
                'NIF'       => '1234567890',
                'adresse'   => 'Andraharo, Antananarivo',
                'contact'   => '+261 34 000 0001',
                'actif'     => true,
            ],
            [
                'nom'       => 'JIRAMA',
                'reference' => 'CLT-002',
                'NIF'       => '9876543210',
                'adresse'   => 'Analakely, Antananarivo',
                'contact'   => '+261 34 000 0002',
                'actif'     => true,
            ],
            [
                'nom'       => 'Leader Price Madagascar',
                'reference' => 'CLT-003',
                'adresse'   => 'Behoririka, Antananarivo',
                'contact'   => '+261 34 000 0003',
                'actif'     => true,
            ],
        ];

        foreach ($clients as $data) {
            Client::firstOrCreate(
                ['reference' => $data['reference']],
                $data
            );
        }

        $this->command->info('   → ' . count($clients) . ' clients créés.');
    }

    // ──────────────────────────────────────────────────────
    private function creerFournisseurs(): void
    {
        $fournisseurs = [
            [
                'nom'       => 'CMP Matières Internationales',
                'reference' => 'FRN-001',
                'adresse'   => 'Zone industrielle, Antananarivo',
                'contact'   => '+261 34 000 1001',
                'actif'     => true,
            ],
            [
                'nom'       => 'PET Supply Madagascar',
                'reference' => 'FRN-002',
                'adresse'   => 'Tanjombato, Antananarivo',
                'contact'   => '+261 34 000 1002',
                'actif'     => true,
            ],
        ];

        foreach ($fournisseurs as $data) {
            Fournisseur::firstOrCreate(
                ['reference' => $data['reference']],
                $data
            );
        }

        $this->command->info('   → ' . count($fournisseurs) . ' fournisseurs créés.');
    }

    // ──────────────────────────────────────────────────────
    private function creerMatieresPremieres(): void
    {
        $matieres = [
            [
                'reference'  => 'MP-001',
                'nom'        => 'Préformes PET 28g',
                'type'       => 'preformes',
                'unite'      => 'piece',
                'prix_moyen' => 250.00,
                'actif'      => true,
            ],
            [
                'reference'  => 'MP-002',
                'nom'        => 'Granulés HDPE vierge',
                'type'       => 'vierge',
                'unite'      => 'kg',
                'prix_moyen' => 3_500.00,
                'actif'      => true,
            ],
            [
                'reference'  => 'MP-003',
                'nom'        => 'HDPE broyé recyclé',
                'type'       => 'broyee',
                'unite'      => 'kg',
                'prix_moyen' => 1_800.00,
                'actif'      => true,
            ],
            [
                'reference'  => 'MP-004',
                'nom'        => 'Colorant masterbatch blanc',
                'type'       => 'colorant',
                'unite'      => 'kg',
                'prix_moyen' => 8_000.00,
                'actif'      => true,
            ],
            [
                'reference'  => 'MP-005',
                'nom'        => 'Chutes PP injection',
                'type'       => 'brute',
                'unite'      => 'kg',
                'prix_moyen' => 500.00,
                'actif'      => true,
            ],
        ];

        foreach ($matieres as $data) {
            MatierePremiere::firstOrCreate(
                ['reference' => $data['reference']],
                $data
            );
        }

        $this->command->info('   → ' . count($matieres) . ' matières premières créées.');
    }

    // ──────────────────────────────────────────────────────
    private function creerProduits(): void
    {
        $catPet  = CategorieProduit::where('nom', CategorieProduit::PET)->first();
        $catHdpe = CategorieProduit::where('nom', CategorieProduit::HDPE)->first();
        $catInj  = CategorieProduit::where('nom', CategorieProduit::INJ)->first();

        $produits = [
            [
                'nomencla'     => 'PF-PET-001',
                'designation'  => 'Bouteille PET 1.5L eau plate',
                'categorie_id' => $catPet->id,
                'contenance'   => '1.5L',
                'unite'        => 'piece',
                'colisage'     => 144,
                'poids'        => '28g',
                'actif'        => true,
                'qualites'     => ['1er' => 450.00, '2e' => 280.00, 'casse' => 80.00],
            ],
            [
                'nomencla'     => 'PF-HDPE-001',
                'designation'  => 'Bidon HDPE 5L blanc',
                'categorie_id' => $catHdpe->id,
                'contenance'   => '5L',
                'unite'        => 'piece',
                'colisage'     => 12,
                'poids'        => '320g',
                'actif'        => true,
                'qualites'     => ['1er' => 2_800.00, '2e' => 1_500.00, 'casse' => 300.00],
            ],
            [
                'nomencla'     => 'PF-INJ-001',
                'designation'  => 'Seau 10L avec couvercle',
                'categorie_id' => $catInj->id,
                'unite'        => 'piece',
                'colisage'     => 6,
                'poids'        => '480g',
                'actif'        => true,
                'qualites'     => ['1er' => 4_200.00, '2e' => 2_500.00, 'casse' => 500.00],
            ],
        ];

        foreach ($produits as $data) {
            $qualites = $data['qualites'];
            unset($data['qualites']);

            $produit = Produit::firstOrCreate(
                ['nomencla' => $data['nomencla']],
                $data
            );

            foreach ($qualites as $qualite => $prix) {
                ClassementProduit::firstOrCreate(
                    [
                        'produit_id' => $produit->id,
                        'qualite'    => $qualite,
                    ],
                    [
                        'prix_specifique' => $prix,
                        'actif'           => true,
                    ]
                );
            }
        }

        $this->command->info('   → ' . count($produits) . ' produits + classements créés.');
    }
}
