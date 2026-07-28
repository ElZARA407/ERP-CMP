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
            ALTER TABLE bon_sorties
            MODIFY motif ENUM(
                'transfert',
                'echantillon',
                'perte',
                'casse',
                'consommation_interne',
                'don',
                'destruction',
                'autre',
                'usage_interne'
            ) NOT NULL
        ");

        Schema::table('bon_sorties', function (Blueprint $table) {
            if (!Schema::hasColumn('bon_sorties', 'destination_location_id')) {
                $table->foreignId('destination_location_id')
                    ->nullable()
                    ->after('client_id')
                    ->constrained('locations')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('bon_sorties', 'motif_detail')) {
                $table->text('motif_detail')->nullable()->after('observations');
            }

            $table->index('destination_location_id', 'idx_bs_destination_location');
        });
    }

    public function down(): void
    {
        Schema::table('bon_sorties', function (Blueprint $table) {
            if (Schema::hasColumn('bon_sorties', 'destination_location_id')) {
                $table->dropConstrainedForeignId('destination_location_id');
            }

            if (Schema::hasColumn('bon_sorties', 'motif_detail')) {
                $table->dropColumn('motif_detail');
            }
        });

        DB::statement("
            ALTER TABLE bon_sorties
            MODIFY motif ENUM(
                'usage_interne',
                'perte',
                'echantillon',
                'don',
                'autre'
            ) NOT NULL
        ");
    }
};