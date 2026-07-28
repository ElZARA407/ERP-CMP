<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE ligne_contrats
            MODIFY frequence ENUM(
                'quotidienne',
                'hebdomadaire',
                'bimensuel',
                'mensuel',
                'tous_x_jours',
                'personnalisee'
            ) NOT NULL
        ");

        Schema::table('ligne_contrats', function (Blueprint $table) {
            if (!Schema::hasColumn('ligne_contrats', 'date_debut')) {
                $table->date('date_debut')->nullable()->after('prix_unitaire');
            }

            if (!Schema::hasColumn('ligne_contrats', 'date_fin')) {
                $table->date('date_fin')->nullable()->after('date_debut');
            }

            if (!Schema::hasColumn('ligne_contrats', 'frequence_jours')) {
                $table->unsignedInteger('frequence_jours')->nullable()->after('date_fin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ligne_contrats', function (Blueprint $table) {
            if (Schema::hasColumn('ligne_contrats', 'frequence_jours')) {
                $table->dropColumn('frequence_jours');
            }

            if (Schema::hasColumn('ligne_contrats', 'date_fin')) {
                $table->dropColumn('date_fin');
            }

            if (Schema::hasColumn('ligne_contrats', 'date_debut')) {
                $table->dropColumn('date_debut');
            }
        });

        DB::statement("
            ALTER TABLE ligne_contrats
            MODIFY frequence ENUM('hebdomadaire', 'bimensuel', 'mensuel') NOT NULL
        ");
    }
};