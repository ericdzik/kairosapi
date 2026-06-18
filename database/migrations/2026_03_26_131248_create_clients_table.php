<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('telephone', 20)->nullable();
            $table->string('quartier', 150)->nullable();
            $table->foreignUuid('commercial_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('commercial_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
