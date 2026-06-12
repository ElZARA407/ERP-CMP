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
        Schema::create('bt_employes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bt_session_id')
                  ->constrained('bt_sessions')
                  ->cascadeOnDelete();
            $table->foreignId('employe_id')
                  ->constrained('employes')
                  ->restrictOnDelete();
            $table->decimal('heures_brutes', 6, 2);
            $table->decimal('heures_effectives', 6, 2)->default(0);
            $table->decimal('taux_horaire', 10, 2); // Historisé
            $table->decimal('cout', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['bt_session_id', 'employe_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bt_employes');
    }
};
