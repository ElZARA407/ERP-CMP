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
        Schema::create('livraisons', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->enum('source_type', ['commande', 'vente_directe']);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('client_id')
                  ->constrained('clients')
                  ->restrictOnDelete();
            $table->string('reference_bc', 30)->nullable();
            $table->string('reference_facture', 30)->nullable();
            $table->date('date_livraison')->nullable();
            $table->enum('statut', ['prepare', 'livre', 'retourne'])
                  ->default('prepare');
            $table->string('chauffeur', 100)->nullable();
            $table->string('vehicule', 30)->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')
                  ->constrained('utilisateurs')
                  ->restrictOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['client_id', 'statut']);
            $table->index(['statut', 'date_livraison']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livraisons');
    }
};
