<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            $table->date('date_livraison')->nullable()->after('date_fin');
            $table->foreignUuid('livre_par_id')->nullable()->after('date_livraison')
                  ->constrained('users')->nullOnDelete();
            $table->text('notes_livraison')->nullable()->after('livre_par_id');
        });
    }

    public function down(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            $table->dropForeign(['livre_par_id']);
            $table->dropColumn(['date_livraison', 'livre_par_id', 'notes_livraison']);
        });
    }
};
