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
        Schema::table('matieres_premieres', function (Blueprint $table) {
            $table->decimal('seuil', 12, 3)
                  ->default(0)
                  ->after('prix_moyen')
                  ->comment("Seuil d'alerte stock matière première");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matieres_premieres', function (Blueprint $table) {
            $table->dropColumn('seuil');
        });
    }
};
