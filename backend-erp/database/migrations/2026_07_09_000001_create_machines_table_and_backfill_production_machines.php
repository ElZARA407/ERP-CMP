<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('machines')) {
            Schema::create('machines', function (Blueprint $table) {
                $table->id();
                $table->string('nom', 100)->unique();
                $table->string('description', 500)->nullable();
                $table->boolean('actif')->default(true);
                $table->timestamps();

                $table->index(['actif', 'nom']);
            });
        }

        if (!Schema::hasColumn('bon_productions', 'machine_id')) {
            Schema::table('bon_productions', function (Blueprint $table) {
                $table->foreignId('machine_id')
                    ->nullable()
                    ->after('produit_id')
                    ->constrained('machines')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('bp_sessions', 'machine_id')) {
            Schema::table('bp_sessions', function (Blueprint $table) {
                $table->foreignId('machine_id')
                    ->nullable()
                    ->after('date_session')
                    ->constrained('machines')
                    ->nullOnDelete();
            });
        }

        DB::transaction(function () {
            $legacyNames = collect()
                ->merge(DB::table('bon_productions')->whereNotNull('machine_production')->pluck('machine_production'))
                ->merge(DB::table('bp_sessions')->whereNotNull('machine_production')->pluck('machine_production'))
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique()
                ->values();

            foreach ($legacyNames as $name) {
                DB::table('machines')->updateOrInsert(
                    ['nom' => $name],
                    [
                        'description' => null,
                        'actif' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            $machineMap = DB::table('machines')->pluck('id', 'nom')->all();

            foreach (
                DB::table('bon_productions')
                    ->whereNull('machine_id')
                    ->whereNotNull('machine_production')
                    ->get(['id', 'machine_production']) as $row
            ) {
                $name = trim((string) $row->machine_production);

                if ($name !== '' && isset($machineMap[$name])) {
                    DB::table('bon_productions')
                        ->where('id', $row->id)
                        ->update(['machine_id' => $machineMap[$name]]);
                }
            }

            foreach (
                DB::table('bp_sessions')
                    ->whereNull('machine_id')
                    ->whereNotNull('machine_production')
                    ->get(['id', 'machine_production']) as $row
            ) {
                $name = trim((string) $row->machine_production);

                if ($name !== '' && isset($machineMap[$name])) {
                    DB::table('bp_sessions')
                        ->where('id', $row->id)
                        ->update(['machine_id' => $machineMap[$name]]);
                }
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('bp_sessions', 'machine_id')) {
            Schema::table('bp_sessions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('machine_id');
            });
        }

        if (Schema::hasColumn('bon_productions', 'machine_id')) {
            Schema::table('bon_productions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('machine_id');
            });
        }

        Schema::dropIfExists('machines');
    }
};