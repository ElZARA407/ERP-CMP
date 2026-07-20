<?php
// database/migrations/2026_01_01_000010_create_fournisseurs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RÔLE : Tiers vendeurs — base du cycle P2P (Purchase-to-Pay).
     *
     * Structure miroir de clients.
     * Pas de code_compta fournisseur dans le schéma original
     * mais conservé pour symétrie et export comptable.
     *
     * LARAVEL 13 :
     * - softDeletes() : désactivation sans perte historique achats
     */
    public function up(): void
    {
        Schema::create('fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);
            $table->string('reference', 30)->unique();
            $table->string('NIF', 50)->nullable();
            $table->string('STAT', 50)->nullable();
            $table->boolean('est_divers')->default(false);
            $table->text('adresse');
            $table->string('email', 150)->nullable();
            $table->string('contact', 30);
            $table->string('interlocutaire', 150)->nullable();
            $table->string('code_compta', 20)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('actif');
            $table->fullText('nom');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fournisseurs');
    }
};