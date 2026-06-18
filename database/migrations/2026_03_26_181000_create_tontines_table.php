<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tontines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('produit_id')->constrained('produits')->restrictOnDelete();
            $table->foreignUuid('commercial_id')->constrained('users')->restrictOnDelete();
            $table->integer('duree_mois')->default(1);
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->enum('statut', ['en_cours', 'termine', 'livre', 'suspendu'])->default('en_cours');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('client_id');
            $table->index('commercial_id');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tontines');
    }
};
