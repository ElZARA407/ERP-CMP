<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bon_transformations', function (Blueprint $table) {
            if (Schema::hasColumn('bon_transformations', 'matiere_broyee_id')) {
                $table->dropForeign(['matiere_broyee_id']);
                $table->dropColumn('matiere_broyee_id');
            }

            if (!Schema::hasColumn('bon_transformations', 'machine_id')) {
                $table->foreignId('machine_id')
                    ->nullable()
                    ->after('matiere_brute_id')
                    ->constrained('machines')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('bon_transformations', 'observations')) {
                $table->text('observations')->nullable()->after('quantite_entree');
            }
        });

        Schema::table('bt_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('bt_sessions', 'machine_id')) {
                $table->foreignId('machine_id')
                    ->nullable()
                    ->after('date_session')
                    ->constrained('machines')
                    ->nullOnDelete();
            }
        });

        Schema::create('bt_session_calculs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bt_session_id')
                ->unique()
                ->constrained('bt_sessions')
                ->cascadeOnDelete();

            $table->decimal('quantite_brute_utilisee', 12, 3)->default(0);
            $table->decimal('quantite_restituee', 12, 3)->default(0);
            $table->decimal('quantite_nette_consomme', 12, 3)->default(0);
            $table->decimal('quantite_broyee_obtenue', 12, 3)->default(0);
            $table->decimal('perte', 12, 3)->default(0);
            $table->decimal('rendement', 8, 3)->default(0);
            $table->decimal('taux_perte', 8, 3)->default(0);

            $table->decimal('temps_brut', 8, 2)->default(0);
            $table->decimal('temps_pause', 8, 2)->default(0);
            $table->decimal('temps_panne', 8, 2)->default(0);
            $table->decimal('temps_autre', 8, 2)->default(0);
            $table->decimal('temps_effectif', 8, 2)->default(0);

            $table->json('details_json')->nullable();
            $table->timestamp('calcule_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bt_session_calculs');

        Schema::table('bt_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('bt_sessions', 'machine_id')) {
                $table->dropForeign(['machine_id']);
                $table->dropColumn('machine_id');
            }
        });

        Schema::table('bon_transformations', function (Blueprint $table) {
            if (Schema::hasColumn('bon_transformations', 'machine_id')) {
                $table->dropForeign(['machine_id']);
                $table->dropColumn('machine_id');
            }

            if (Schema::hasColumn('bon_transformations', 'observations')) {
                $table->dropColumn('observations');
            }

            if (!Schema::hasColumn('bon_transformations', 'matiere_broyee_id')) {
                $table->foreignId('matiere_broyee_id')
                    ->nullable()
                    ->constrained('matieres_premieres')
                    ->nullOnDelete();
            }
        });
    }
};