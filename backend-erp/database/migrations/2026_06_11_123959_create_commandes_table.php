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
        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->foreignId('client_id')
                  ->constrained('clients')
                  ->restrictOnDelete();
            $table->date('date');
            $table->date('date_livraison_prevue')->nullable();
            $table->foreignId('location_id')
                  ->constrained('locations')
                  ->restrictOnDelete();
            // CORRECTION : ENUM correct sans guillemets parasites
            $table->enum('statut', ['livree', 'non_livree', 'partielle'])
                  ->default('non_livree');
            $table->unsignedSmallInteger('echeance')->default(30);
            $table->foreignId('created_by')
                  ->constrained('utilisateurs')
                  ->restrictOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'statut']);
            $table->index(['statut', 'date']);
            $table->index('location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commandes');
    }
};
