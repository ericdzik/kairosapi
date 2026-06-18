<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tontine_produits', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tontine_id')->constrained('tontines')->cascadeOnDelete();
            $table->foreignUuid('produit_id')->constrained('produits')->restrictOnDelete();
            $table->integer('quantite')->default(1);
            $table->decimal('prix_unitaire', 12, 2)->comment('Prix au moment de la souscription');
            $table->decimal('sous_total', 12, 2)->comment('quantite * prix_unitaire');
            $table->timestamps();

            $table->unique(['tontine_id', 'produit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tontine_produits');
    }
};
