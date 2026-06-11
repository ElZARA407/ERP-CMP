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
        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);
            $table->string('email', 150)->unique();
            $table->string('password', 255);
            $table->foreignId('role_id')
                  ->constrained('roles')
                  ->restrictOnDelete();
            $table->foreignId('location_id')
                  ->constrained('locations')
                  ->restrictOnDelete();
            $table->boolean('actif')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['role_id', 'actif']);
            $table->index('location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('utilisateurs');
    }
};
