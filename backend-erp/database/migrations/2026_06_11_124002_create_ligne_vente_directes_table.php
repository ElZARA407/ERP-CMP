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
        Schema::create('lignes_vente_directe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vente_directe_id')
                  ->constrained('ventes_directes')
                  ->cascadeOnDelete();
            $table->foreignId('classement_id')
                  ->constrained('classement_produits')
                  ->restrictOnDelete();
            $table->decimal('quantite', 12, 3);
            $table->decimal('prix_unitaire', 12, 2);
            $table->decimal('total_ligne', 14, 2)->default(0);
            $table->timestamps();

            $table->index('vente_directe_id');
            $table->index('classement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_vente_directe');
    }
};
