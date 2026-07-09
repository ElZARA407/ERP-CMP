<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE bon_productions MODIFY machine_production VARCHAR(100) NULL");
        DB::statement("ALTER TABLE bp_sessions MODIFY machine_production VARCHAR(100) NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE bon_productions SET machine_production = '' WHERE machine_production IS NULL");
        DB::statement("UPDATE bp_sessions SET machine_production = '' WHERE machine_production IS NULL");

        DB::statement("ALTER TABLE bon_productions MODIFY machine_production VARCHAR(100) NOT NULL");
        DB::statement("ALTER TABLE bp_sessions MODIFY machine_production VARCHAR(100) NOT NULL");
    }
};