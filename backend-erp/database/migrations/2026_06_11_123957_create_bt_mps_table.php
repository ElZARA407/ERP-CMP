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
        Schema::create('bt_mps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bt_session_id')
                  ->constrained('bt_sessions')
                  ->cascadeOnDelete();
            $table->foreignId('matiere_id')
                  ->constrained('matieres_premieres')
                  ->restrictOnDelete();
            $table->enum('type', ['entree', 'sortie']);
            $table->decimal('quantite', 12, 3);
            $table->decimal('quantite_restituee', 12, 3)->default(0);
            $table->timestamps();

            $table->index(['bt_session_id', 'type']);
            $table->index('matiere_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bt_mps');
    }
};
