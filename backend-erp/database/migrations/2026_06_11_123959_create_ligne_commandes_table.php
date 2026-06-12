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
        Schema::create('ligne_commandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_id')
                  ->constrained('commandes')
                  ->cascadeOnDelete();
            $table->foreignId('classement_id')
                  ->constrained('classement_produits')
                  ->restrictOnDelete();
            $table->decimal('quantite', 12, 3);
            $table->decimal('quantite_restante', 12, 3)->default(0);
            $table->decimal('prix_unitaire', 12, 2);
            $table->enum('etat', ['disponible', 'indisponible', 'en_cours'])
                  ->default('disponible');
            $table->timestamps();

            $table->index(['commande_id', 'etat']);
            $table->index('classement_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ligne_commandes');
    }
};
