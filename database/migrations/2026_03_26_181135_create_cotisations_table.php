<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotisations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tontine_id')->constrained('tontines')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignUuid('commercial_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('validateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('nombre_mises')->default(1);
            $table->decimal('montant_unitaire', 12, 2);
            $table->decimal('montant_total', 12, 2);
            $table->date('date_cotisation');
            $table->enum('statut', ['en_attente', 'valide', 'rejete', 'annule'])->default('en_attente');
            $table->text('motif_rejet')->nullable();
            $table->timestamp('valide_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tontine_id');
            $table->index('client_id');
            $table->index('commercial_id');
            $table->index('statut');
            $table->index('date_cotisation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotisations');
    }
};
