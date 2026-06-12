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
        Schema::create('bon_productions', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->date('date');
            $table->foreignId('location_id')
                  ->constrained('locations')
                  ->restrictOnDelete();
            $table->foreignId('produit_id')
                  ->constrained('produits')
                  ->restrictOnDelete();
            $table->string('machine_production', 100);
            $table->decimal('quantite_cible', 12, 3);
            $table->enum('statut', ['ouvert', 'en_cours', 'cloture', 'annule'])
                  ->default('ouvert');
            $table->decimal('cout_total', 14, 2)->default(0);
            $table->foreignId('created_by')
                  ->constrained('utilisateurs')
                  ->restrictOnDelete();
            $table->timestamps();

            $table->index(['statut', 'date']);
            $table->index(['location_id', 'statut']);
            $table->index('produit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bon_productions');
    }
};
