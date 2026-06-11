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
        Schema::create('employes', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 20)->unique();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->foreignId('poste_id')
                  ->constrained('postes')
                  ->restrictOnDelete();
            $table->date('date_embauche');
            $table->date('date_depart')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['actif', 'poste_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employes');
    }
};
