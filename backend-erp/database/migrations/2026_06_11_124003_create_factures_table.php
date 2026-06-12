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
        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->foreignId('livraison_id')
                  ->constrained('livraisons')
                  ->restrictOnDelete();
            $table->foreignId('client_id')
                  ->constrained('clients')
                  ->restrictOnDelete();
            $table->date('date');
            $table->decimal('total', 14, 2);
            $table->enum('statut', [
                'en_attente',
                'emise',
                'partiellement_payee',
                'payee',
                'annulee',
            ])->default('en_attente');
            $table->date('echeance_paiement')->nullable();
            $table->date('date_paiement')->nullable();
            $table->enum('mode_paiement', [
                'espece',
                'virement',
                'cheque',
                'mobile_money',
            ])->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                  ->constrained('utilisateurs')
                  ->restrictOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'statut']);
            $table->index(['statut', 'date']);
            $table->index('echeance_paiement');
            $table->index('livraison_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factures');
    }
};
