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
        Schema::create('ligne_contrats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrat_id')
                  ->constrained('contrats')
                  ->cascadeOnDelete();
            $table->foreignId('classement_id')
                  ->constrained('classement_produits')
                  ->restrictOnDelete();
            $table->decimal('quantite_contractuelle', 12, 3);
            $table->decimal('quantite_livree_ytd', 12, 3)->default(0);
            $table->enum('frequence', ['hebdomadaire', 'bimensuel', 'mensuel']);
            $table->enum('statut', ['disponible', 'indisponible', 'en_cours'])
                  ->default('disponible');
            $table->decimal('prix_unitaire', 12, 2);
            $table->timestamps();

            $table->index('contrat_id');
            $table->index('classement_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ligne_contrats');
    }
};
