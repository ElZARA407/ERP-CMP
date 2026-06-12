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
        Schema::create('bt_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bon_transformation_id')
                  ->constrained('bon_transformations')
                  ->cascadeOnDelete();
            $table->unsignedInteger('session_numero');
            $table->date('date_session');
            $table->string('machine_broyage', 100);
            $table->decimal('ecarts', 5, 2)->default(0);
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

            // CORRECTION CRITIQUE : unicité session par ODB
            $table->unique(
                ['bon_transformation_id', 'session_numero'],
                'uq_bt_session_numero'
            );
            $table->index(['bon_transformation_id', 'statut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bt_sessions');
    }
};
