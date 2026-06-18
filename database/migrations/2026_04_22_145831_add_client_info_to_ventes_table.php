<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->string('client_nom', 200)->nullable()->after('commercial_id')
                  ->comment('Nom du client si non enregistré');
            $table->string('client_telephone', 20)->nullable()->after('client_nom')
                  ->comment('Téléphone du client si non enregistré');
        });
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn(['client_nom', 'client_telephone']);
        });
    }
};
