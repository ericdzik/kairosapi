<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignUuid('produit_id')->constrained('produits')->restrictOnDelete();
            $table->foreignUuid('commercial_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('validateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('quantite')->default(1);
            $table->decimal('montant', 12, 2);
            $table->date('date_vente');
            $table->enum('statut', ['en_attente', 'valide', 'annule'])->default('en_attente');
            $table->text('motif_annulation')->nullable();
            $table->timestamp('valide_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('client_id');
            $table->index('commercial_id');
            $table->index('statut');
            $table->index('date_vente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventes');
    }
};
