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
        Schema::create('demandes_achat', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->date('date_demande');
            $table->foreignId('demandeur_id')
                  ->constrained('utilisateurs')
                  ->restrictOnDelete();
            $table->enum('statut', ['brouillon', 'soumise', 'approuvee', 'rejetee'])
                  ->default('brouillon');
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index(['statut', 'date_demande']);
            $table->index('demandeur_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demandes_achat');
    }
};
