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
        Schema::create('bp_mps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bp_session_id')
                  ->constrained('bp_sessions')
                  ->cascadeOnDelete();
            $table->foreignId('matiere_id')
                  ->constrained('matieres_premieres')
                  ->restrictOnDelete();
            $table->decimal('quantite_utilisee', 12, 3);
            $table->decimal('quantite_restituee', 12, 3)->default(0);
            $table->decimal('cout_matiere', 12, 2)->default(0);
            $table->timestamps();

            $table->index('bp_session_id');
            $table->index('matiere_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bp_mps');
    }
};
