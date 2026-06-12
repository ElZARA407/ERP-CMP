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
        Schema::create('ligne_sorties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bon_sortie_id')
                  ->constrained('bon_sorties')
                  ->cascadeOnDelete();
            $table->foreignId('classement_id')
                  ->constrained('classement_produits')
                  ->restrictOnDelete();
            $table->decimal('quantite', 12, 3);
            $table->timestamps();

            $table->index('bon_sortie_id');
            $table->index('classement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ligne_sorties');
    }
};
