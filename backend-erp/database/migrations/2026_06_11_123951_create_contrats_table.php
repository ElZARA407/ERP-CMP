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
        Schema::create('contrats', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->foreignId('client_id')
                  ->constrained('clients')
                  ->restrictOnDelete();
            // CORRECTION : VARCHAR(7) format YYYY-MM
            $table->string('mois', 7);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index(['client_id', 'mois']);
            $table->index(['mois', 'actif']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contrats');
    }
};
