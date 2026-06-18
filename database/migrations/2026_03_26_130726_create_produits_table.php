<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produits', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->string('code', 20)->unique();
            $table->string('nom', 150);
            $table->string('categorie', 100)->nullable();
            $table->decimal('prix_unitaire', 12, 2);
            $table->integer('duree_mois')->default(1)->comment('Durée du contrat en mois (1-12)');
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('actif');
            $table->index('categorie');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
