<?php

namespace App\Console\Commands;

use App\Models\Tontine;
use Illuminate\Console\Command;

class RecalculerTontines extends Command
{
    protected $signature   = 'kairos:recalculer-tontines
                                {--dry-run : Afficher sans modifier}';
    protected $description = 'Recalcule le statut des tontines en_cours dont la progression >= 100%';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $tontines = Tontine::where('statut', 'en_cours')->get();
        $this->info("🔍 {$tontines->count()} tontine(s) en_cours à vérifier...");

        $count = 0;
        foreach ($tontines as $tontine) {
            $progression = $tontine->progression();

            if ($progression >= 100.0) {
                $count++;
                $client = $tontine->client;
                $nom    = $client ? "{$client->nom} {$client->prenom}" : $tontine->client_id;

                if ($dryRun) {
                    $this->line("  [dry-run] Tontine #{$tontine->id} — {$nom} — progression: {$progression}% → termine");
                } else {
                    $tontine->update(['statut' => 'termine']);
                    $this->info("  ✅ Tontine de {$nom} passée en 'termine' (progression: {$progression}%)");
                }
            }
        }

        if ($count === 0) {
            $this->info('✅ Aucune tontine à mettre à jour.');
        } else {
            $this->info($dryRun
                ? "📋 {$count} tontine(s) seraient mises à jour."
                : "✅ {$count} tontine(s) mises à jour.");
        }

        return self::SUCCESS;
    }
}
