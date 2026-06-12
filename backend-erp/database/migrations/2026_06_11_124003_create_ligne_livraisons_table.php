<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lignes_livraison', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livraison_id')
                  ->constrained('livraisons')
                  ->cascadeOnDelete();
            $table->foreignId('ligne_commande_id')
                  ->nullable()
                  ->constrained('lignes_commande')
                  ->nullOnDelete();
            $table->foreignId('ligne_vente_directe_id')
                  ->nullable()
                  ->constrained('lignes_vente_directe')
                  ->nullOnDelete();
            $table->foreignId('classement_id')
                  ->constrained('classement_produits')
                  ->restrictOnDelete();
            $table->decimal('quantite_livree', 12, 3);
            $table->timestamps();

            $table->index('livraison_id');
            $table->index('ligne_commande_id');
            $table->index('ligne_vente_directe_id');
            $table->index('classement_id');
        });

        // CHECK XOR MySQL 8 : exactement une source de ligne
        DB::statement("
            ALTER TABLE lignes_livraison
            ADD CONSTRAINT chk_ligne_livraison_source_xor
            CHECK (
                (
                    ligne_commande_id IS NOT NULL
                    AND ligne_vente_directe_id IS NULL
                )
                OR
                (
                    ligne_commande_id IS NULL
                    AND ligne_vente_directe_id IS NOT NULL
                )
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_livraison');
    }
};
