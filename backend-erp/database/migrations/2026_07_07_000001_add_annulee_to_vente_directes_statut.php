<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE ventes_directes
            MODIFY statut ENUM('brouillon', 'validee', 'livree', 'annulee')
            NOT NULL DEFAULT 'brouillon'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE ventes_directes
            MODIFY statut ENUM('brouillon', 'validee', 'livree')
            NOT NULL DEFAULT 'brouillon'
        ");
    }
};