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
        Schema::create('lignes_achat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_achat_id')
                  ->constrained('journal_achats')
                  ->cascadeOnDelete();
            $table->foreignId('matiere_id')
                  ->constrained('matieres_premieres')
                  ->restrictOnDelete();
            $table->decimal('quantite', 12, 3);
            $table->decimal('prix_unitaire', 12, 2);
            $table->decimal('total_ligne', 14, 2)->default(0);
            $table->text('observations_ligne')->nullable();
            $table->timestamps();

            $table->index('journal_achat_id');
            $table->index('matiere_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lignes_achat');
    }
};
