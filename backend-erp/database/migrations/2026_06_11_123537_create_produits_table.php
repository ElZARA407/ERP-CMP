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
        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->string('nomencla', 30)->unique();
            $table->string('designation', 150);
            $table->foreignId('categorie_id')
                  ->constrained('categorie_produits')
                  ->restrictOnDelete();
            $table->string('contenance', 20)->nullable();
            $table->string('format', 20)->nullable();
            $table->string('unite', 10);
            $table->decimal('colisage', 12, 2);
            $table->string('poids', 10);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['categorie_id', 'actif']);
            $table->fullText('designation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
