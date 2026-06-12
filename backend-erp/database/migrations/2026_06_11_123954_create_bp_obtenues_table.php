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
        Schema::create('bp_obtenues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bp_session_id')
                  ->constrained('bp_sessions')
                  ->cascadeOnDelete();
            $table->foreignId('classement_id')
                  ->constrained('classement_produits')
                  ->restrictOnDelete();
            $table->decimal('quantite_produite', 12, 3);
            $table->foreignId('destination_location_id')
                  ->constrained('locations')
                  ->restrictOnDelete();
            $table->timestamps();

            $table->index(['bp_session_id', 'classement_id']);
            $table->index('destination_location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bp_obtenues');
    }
};
