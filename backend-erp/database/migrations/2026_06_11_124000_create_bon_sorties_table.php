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
        Schema::create('bon_sorties', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->foreignId('location_id')
                  ->constrained('locations')
                  ->restrictOnDelete();
            $table->date('date');
            $table->enum('motif', [
                'usage_interne',
                'perte',
                'echantillon',
                'don',
                'autre',
            ]);
            $table->foreignId('client_id')
                  ->nullable()
                  ->constrained('clients')
                  ->nullOnDelete();
            $table->enum('statut', ['brouillon', 'valide'])
                  ->default('brouillon');
            $table->text('observations')->nullable();
            $table->foreignId('created_by')
                  ->constrained('utilisateurs')
                  ->restrictOnDelete();
            $table->foreignId('valide_by')
                  ->nullable()
                  ->constrained('utilisateurs')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index(['statut', 'date']);
            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bons_sortie');
    }
};
