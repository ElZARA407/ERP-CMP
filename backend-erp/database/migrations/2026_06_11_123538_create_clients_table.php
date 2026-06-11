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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);
            $table->string('reference', 30)->unique();
            $table->string('NIF', 50)->nullable();
            $table->string('STAT', 50)->nullable();
            $table->text('adresse');
            $table->string('email', 150)->nullable();
            $table->string('contact', 30);
            $table->string('interlocutaire', 150)->nullable();
            $table->string('code_compta', 20)->nullable();
            $table->string('facturation', 20)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('actif');
            $table->fullText('nom');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
