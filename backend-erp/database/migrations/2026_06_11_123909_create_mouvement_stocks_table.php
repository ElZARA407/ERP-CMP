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
        Schema::create('mouvements_stock', function (Blueprint $table) {
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
            $table->enum('type', ['entree', 'sortie', 'retour']);
            $table->decimal('quantite', 12, 3);
            $table->string('reference_type', 50);
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('utilisateur_id')
                  ->constrained('utilisateurs')
                  ->restrictOnDelete();
            $table->dateTime('date_mouvement');

            // Journal immuable : pas de updated_at
            $table->timestamp('created_at')->useCurrent();

            // Index pour historique et rapports
            $table->index(
                ['entite_type', 'entite_id', 'date_mouvement'],
                'idx_mouvement_entite_date'
            );
            $table->index(
                ['reference_type', 'reference_id'],
                'idx_mouvement_reference'
            );
            $table->index(
                ['location_id', 'date_mouvement'],
                'idx_mouvement_location_date'
            );
            $table->index('utilisateur_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_stock');
    }
};
