<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'ligne_commandes',
            'lignes_livraison',
            'ligne_factures',
            'lignes_vente_directe',
            'ligne_sorties',
            'ligne_contrats',
            'bp_obtenues',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('produit_id')
                    ->nullable()
                    ->after('classement_id')
                    ->constrained('produits')
                    ->restrictOnDelete();

                $table->index('produit_id');
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'ligne_commandes',
            'lignes_livraison',
            'ligne_factures',
            'lignes_vente_directe',
            'ligne_sorties',
            'ligne_contrats',
            'bp_obtenues',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign([$tableName === 'ligne_commandes' ? 'produit_id' : 'produit_id']);
                $table->dropIndex([$tableName === 'ligne_commandes' ? 'produit_id' : 'produit_id']);
                $table->dropColumn('produit_id');
            });
        }
    }
};