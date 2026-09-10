<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotisations', function (Blueprint $table) {
            // Montant réellement versé par le client (peut être partiel)
            // Si null, on considère que le montant_total a été versé intégralement
            $table->decimal('montant_verse', 12, 2)->nullable()->after('montant_total')
                  ->comment('Montant réellement versé — si null = montant_total intégral');
        });
    }

    public function down(): void
    {
        Schema::table('cotisations', function (Blueprint $table) {
            $table->dropColumn('montant_verse');
        });
    }
};
