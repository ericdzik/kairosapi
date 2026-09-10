<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            // Rendre client_id nullable pour les ventes à des clients occasionnels
            $table->foreignUuid('client_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->foreignUuid('client_id')->nullable(false)->change();
        });
    }
};
