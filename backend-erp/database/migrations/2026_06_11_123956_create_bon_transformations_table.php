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
        Schema::create('bon_transformations', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->date('date');
            $table->foreignId('location_id')
                  ->constrained('locations')
                  ->restrictOnDelete();
            $table->foreignId('matiere_brute_id')
                  ->constrained('matieres_premieres')
                  ->restrictOnDelete();
            $table->foreignId('matiere_broyee_id')
                  ->constrained('matieres_premieres')
                  ->restrictOnDelete();
            $table->string('machine_broyage', 100);
            $table->decimal('quantite_entree', 12, 3);
            $table->enum('statut', ['ouvert', 'en_cours', 'cloture', 'annule'])
                  ->default('ouvert');
            $table->foreignId('created_by')
                  ->constrained('utilisateurs')
                  ->restrictOnDelete();
            $table->foreignId('saisi_by')
                  ->nullable()
                  ->constrained('utilisateurs')
                  ->nullOnDelete();
            $table->foreignId('valide_by')
                  ->nullable()
                  ->constrained('utilisateurs')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index(['statut', 'date']);
            $table->index('location_id');
            $table->index('matiere_brute_id');
            $table->index('matiere_broyee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bon_transformations');
    }
};
