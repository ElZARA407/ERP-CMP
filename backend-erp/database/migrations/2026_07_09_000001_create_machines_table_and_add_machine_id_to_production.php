<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::table('bon_productions', function (Blueprint $table) {
            $table->foreignId('machine_id')
                ->nullable()
                ->after('location_id')
                ->constrained('machines')
                ->nullOnDelete();
        });

        Schema::table('bp_sessions', function (Blueprint $table) {
            $table->foreignId('machine_id')
                ->nullable()
                ->after('bon_production_id')
                ->constrained('machines')
                ->nullOnDelete();
        });

        DB::transaction(function () {
            $machineNames = collect()
                ->merge(DB::table('bon_productions')->whereNotNull('machine_production')->pluck('machine_production'))
                ->merge(DB::table('bp_sessions')->whereNotNull('machine_production')->pluck('machine_production'))
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique()
                ->values();

            $now = now();

            foreach ($machineNames as $machineName) {
                DB::table('machines')->updateOrInsert(
                    ['nom' => $machineName],
                    [
                        'description' => null,
                        'actif' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            foreach (DB::table('bon_productions')->select('id', 'machine_production')->get() as $row) {
                $nom = trim((string) ($row->machine_production ?? ''));
                if ($nom === '') {
                    continue;
                }

                $machineId = DB::table('machines')->where('nom', $nom)->value('id');
                if ($machineId) {
                    DB::table('bon_productions')
                        ->where('id', $row->id)
                        ->update(['machine_id' => $machineId]);
                }
            }

            foreach (DB::table('bp_sessions')->select('id', 'machine_production')->get() as $row) {
                $nom = trim((string) ($row->machine_production ?? ''));
                if ($nom === '') {
                    continue;
                }

                $machineId = DB::table('machines')->where('nom', $nom)->value('id');
                if ($machineId) {
                    DB::table('bp_sessions')
                        ->where('id', $row->id)
                        ->update(['machine_id' => $machineId]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('bp_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('machine_id');
        });

        Schema::table('bon_productions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('machine_id');
        });

        Schema::dropIfExists('machines');
    }
};