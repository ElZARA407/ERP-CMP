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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')
                  ->constrained('locations')
                  ->restrictOnDelete();
            $table->enum('entite_type', ['matiere', 'produit']);
            $table->unsignedBigInteger('entite_id');
            $table->foreignId('classement_id')
                  ->nullable()
                  ->constrained('classement_produits')
                  ->nullOnDelete();
            $table->decimal('stock_total', 12, 3)->default(0);
            $table->timestamps();

            // CORRECTION CRITIQUE : unicité ligne de stock par entité et site
            $table->unique(
                ['location_id', 'entite_type', 'entite_id', 'classement_id'],
                'uq_stock_entite'
            );

            // Index de performance pour requêtes de niveau stock
            $table->index(['entite_type', 'entite_id']);
            $table->index(['location_id', 'entite_type']);
        });

        // CHECK MySQL 8 : validation du type polymorphique au niveau base
        DB::statement("
            ALTER TABLE stocks
            ADD CONSTRAINT chk_stock_entite_type
            CHECK (entite_type IN ('matiere', 'produit'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
