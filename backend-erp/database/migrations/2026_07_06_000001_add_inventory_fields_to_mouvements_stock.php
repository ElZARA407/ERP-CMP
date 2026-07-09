<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->string('motif', 500)->nullable()->after('reference_id');
            $table->decimal('stock_theorique', 12, 3)->nullable()->after('motif');
            $table->decimal('stock_physique', 12, 3)->nullable()->after('stock_theorique');
            $table->decimal('ecart', 12, 3)->nullable()->after('stock_physique');

            $table->index(['type', 'date_mouvement'], 'idx_mouvement_type_date');
        });
    }

    public function down(): void
    {
        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->dropIndex('idx_mouvement_type_date');
            $table->dropColumn(['motif', 'stock_theorique', 'stock_physique', 'ecart']);
        });
    }
};