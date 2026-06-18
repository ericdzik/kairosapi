<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->uuid('user_id');
            $table->string('titre', 150);
            $table->text('message');
            $table->string('type', 50); // mise_attente, mise_validee, mise_rejetee, livraison, retard, anomalie, sync
            $table->uuid('entite_id')->nullable(); // pour deep link mobile
            $table->boolean('lu')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'lu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
