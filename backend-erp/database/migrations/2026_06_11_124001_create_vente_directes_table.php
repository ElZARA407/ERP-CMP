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
        Schema::create('ventes_directes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->foreignId('client_id')
                  ->constrained('clients')
                  ->restrictOnDelete();
            $table->date('date');
            $table->foreignId('location_id')
                  ->constrained('locations')
                  ->restrictOnDelete();
            $table->enum('statut', ['brouillon', 'validee', 'livree'])
                  ->default('brouillon');
            $table->decimal('total', 14, 2)->default(0);
            $table->foreignId('created_by')
                  ->constrained('utilisateurs')
                  ->restrictOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'statut']);
            $table->index(['statut', 'date']);
            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventes_directes');
    }
};
