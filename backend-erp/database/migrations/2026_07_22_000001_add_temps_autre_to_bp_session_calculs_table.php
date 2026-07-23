<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bp_session_calculs', function (Blueprint $table) {
            $table->decimal('temps_autre', 8, 2)->default(0)->after('temps_panne');
        });
    }

    public function down(): void
    {
        Schema::table('bp_session_calculs', function (Blueprint $table) {
            $table->dropColumn('temps_autre');
        });
    }
};