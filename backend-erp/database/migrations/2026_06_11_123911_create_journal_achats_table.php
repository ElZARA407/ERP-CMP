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
        Schema::create('journal_achats', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->foreignId('fournisseur_id')
                  ->constrained('fournisseurs')
                  ->restrictOnDelete();
            $table->date('date');
            $table->foreignId('location_id')
                  ->constrained('locations')
                  ->restrictOnDelete();
            $table->string('vehicule', 30)->nullable();
            $table->enum('statut', ['brouillon', 'valide'])
                  ->default('brouillon');
            $table->decimal('total', 14, 2)->default(0);
            $table->text('observations')->nullable();
            $table->foreignId('created_by')
                  ->constrained('utilisateurs')
                  ->restrictOnDelete();
            $table->foreignId('valide_by')
                  ->nullable()
                  ->constrained('utilisateurs')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index(['fournisseur_id', 'date']);
            $table->index(['statut', 'date']);
            $table->index('location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_achats');
    }
};
