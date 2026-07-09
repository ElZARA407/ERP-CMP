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
        Schema::create('bp_evenements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bp_session_id')
                  ->constrained('bp_sessions')
                  ->cascadeOnDelete();
            $table->enum('type_evenement', ['debut', 'fin', 'panne', 'autre']);
            $table->time('heure_debut');
            $table->time('heure_fin')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('operateur_id')
                  ->constrained('utilisateurs')
                  ->restrictOnDelete();
            $table->timestamps();

            $table->index(['bp_session_id', 'type_evenement']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bp_evenements');
    }
};
