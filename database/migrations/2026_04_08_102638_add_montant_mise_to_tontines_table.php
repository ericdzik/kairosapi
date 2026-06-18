<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            $table->decimal('montant_mise', 12, 2)->after('duree_mois')
                  ->comment('Montant de la mise journalière défini à la création de la tontine');
        });
    }

    public function down(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            $table->dropColumn('montant_mise');
        });
    }
};
