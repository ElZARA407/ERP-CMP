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
        Schema::create('matieres_premieres', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->string('nom', 150);
            $table->enum('type', [
                'preformes',
                'broyee',
                'brute',
                'vierge',
                'colorant',
                'autre',
            ]);
            $table->text('description')->nullable();
            $table->string('unite', 10);
            $table->decimal('prix_moyen', 12, 2)->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('actif');
            $table->fullText('nom');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matiere_premieres');
    }
};
