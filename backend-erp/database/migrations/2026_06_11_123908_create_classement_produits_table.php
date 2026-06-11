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
        Schema::create('classement_produits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')
                  ->constrained('produits')
                  ->cascadeOnDelete();
            $table->enum('qualite', ['1er', '2e', 'casse']);
            $table->decimal('prix_specifique', 12, 2)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            // CORRECTION CRITIQUE : unicité métier produit × qualité
            $table->unique(
                ['produit_id', 'qualite'],
                'uq_classement_produit_qualite'
            );
            $table->index(['produit_id', 'actif']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classement_produits');
    }
};
