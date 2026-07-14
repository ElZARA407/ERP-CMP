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
        Schema::create('bp_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bon_production_id')
                  ->constrained('bon_productions')
                  ->cascadeOnDelete();
            $table->string('session_numero', 20);   
            $table->date('date_session');
            $table->string('machine_production', 100);
            $table->decimal('cout_electricite', 12, 2)->default(0);
            $table->decimal('cout_total', 14, 2)->default(0);
            $table->enum('statut', ['ouverte', 'validee'])
                  ->default('ouverte');
            $table->foreignId('saisi_by')
                  ->nullable()
                  ->constrained('utilisateurs')
                  ->nullOnDelete();
            $table->foreignId('valide_by')
                  ->nullable()
                  ->constrained('utilisateurs')
                  ->nullOnDelete();
            $table->timestamps();

            // CORRECTION CRITIQUE : unicité session par ODF
            $table->unique(
                ['bon_production_id', 'session_numero'],
                'uq_bp_session_numero'
            );
            $table->index(['bon_production_id', 'statut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bp_sessions');
    }
};
