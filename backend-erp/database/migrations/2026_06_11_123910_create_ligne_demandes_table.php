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
        Schema::create('ligne_demandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_achat_id')
                  ->constrained('demandes_achat')
                  ->cascadeOnDelete();
            $table->enum('entite_type', ['matiere', 'produit']);
            $table->unsignedBigInteger('entite_id');
            $table->decimal('quantite', 12, 3);
            $table->text('observation_ligne')->nullable();
            $table->timestamps();

            $table->index('demande_achat_id');
            $table->index(['entite_type', 'entite_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ligne_demandes');
    }
};
