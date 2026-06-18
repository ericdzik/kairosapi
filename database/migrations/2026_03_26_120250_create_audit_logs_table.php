<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->uuid('user_id')->nullable();
            $table->string('action', 50); // created, updated, deleted, validated, rejected, login...
            $table->string('entite', 50); // User, Client, Cotisation...
            $table->uuid('entite_id')->nullable();
            $table->jsonb('ancienne_valeur')->nullable();
            $table->jsonb('nouvelle_valeur')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('device_id', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['entite', 'entite_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
