<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter prix_vente_directe sur produits
        Schema::table('produits', function (Blueprint $table) {
            $table->decimal('prix_vente_directe', 12, 2)->nullable()->after('prix_unitaire')
                  ->comment('Prix cash pour vente directe');
        });

        // Table des grilles de mise par durée
        Schema::create('produit_grilles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->foreignUuid('produit_id')->constrained('produits')->cascadeOnDelete();
            $table->integer('duree_mois')->comment('Durée en mois : 1 à 12');
            $table->decimal('montant_mise', 12, 2)->comment('Mise journalière pour cette durée');
            $table->timestamps();

            $table->unique(['produit_id', 'duree_mois']);
            $table->index('produit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produit_grilles');
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn('prix_vente_directe');
        });
    }
};
