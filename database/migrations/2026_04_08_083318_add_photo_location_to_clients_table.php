<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('quartier')->comment('Chemin vers la photo du client');
            $table->decimal('latitude', 10, 7)->nullable()->after('photo');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('adresse_complete')->nullable()->after('longitude')->comment('Adresse géocodée');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['photo', 'latitude', 'longitude', 'adresse_complete']);
        });
    }
};
