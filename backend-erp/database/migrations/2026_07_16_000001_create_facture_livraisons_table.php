<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facture_livraisons', function (Blueprint $table) {
            $table->id();

            $table->foreignId('facture_id')
                ->constrained('factures')
                ->cascadeOnDelete();

            $table->foreignId('livraison_id')
                ->constrained('livraisons')
                ->restrictOnDelete();

            $table->decimal('total_livraison', 14, 2)->default(0);
            $table->unsignedInteger('lignes_count')->default(0);

            $table->timestamps();

            $table->unique('livraison_id');
            $table->index('facture_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facture_livraisons');
    }
};