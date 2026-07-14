<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bp_session_calculs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bp_session_id')
                ->unique()
                ->constrained('bp_sessions')
                ->cascadeOnDelete();

            $table->decimal('temps_brut', 10, 2)->default(0);
            $table->decimal('temps_pause', 10, 2)->default(0);
            $table->decimal('temps_panne', 10, 2)->default(0);
            $table->decimal('temps_effectif', 10, 2)->default(0);

            $table->decimal('quantite_totale_produite', 12, 3)->default(0);
            $table->decimal('cout_matieres_total', 14, 2)->default(0);
            $table->decimal('cout_main_oeuvre_total', 14, 2)->default(0);
            $table->decimal('cout_electricite', 14, 2)->default(0);
            $table->decimal('cout_global', 14, 2)->default(0);
            $table->decimal('cout_unitaire', 14, 4)->default(0);

            $table->json('details_json')->nullable();
            $table->timestamp('calcule_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bp_session_calculs');
    }
};
