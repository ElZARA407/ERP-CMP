<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classement_produits', function (Blueprint $table) {
            $table->id();
            $table->enum('qualite', ['1er', '2e', 'casse'])->unique();
            $table->string('libelle')->nullable(); // ex: "1ère qualité", "2ème qualité", "Cassé"
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classement_produits');
    }
};