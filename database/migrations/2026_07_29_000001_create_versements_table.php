<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versements', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->foreignUuid('commercial_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('secretaire_id')->constrained('users')->restrictOnDelete();
            $table->date('date');
            $table->decimal('montant_attendu', 12, 2)->comment('Somme des mises validées du jour');
            $table->decimal('montant_verse', 12, 2)->comment('Montant physiquement remis');
            $table->decimal('ecart', 12, 2)->default(0)->comment('montant_verse - montant_attendu');
            $table->enum('statut', ['conforme', 'ecart_positif', 'ecart_negatif'])->default('conforme');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('commercial_id');
            $table->index('date');
            $table->unique(['commercial_id', 'date']); // un seul versement par commercial par jour
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versements');
    }
};
