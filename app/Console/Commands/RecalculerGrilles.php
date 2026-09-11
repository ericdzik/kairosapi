<?php

namespace App\Console\Commands;

use App\Models\Produit;
use Illuminate\Console\Command;

class RecalculerGrilles extends Command
{
    protected $signature   = 'kairos:recalculer-grilles
                                {--dry-run : Afficher sans modifier}';
    protected $description = 'Recalcule montant_mise = prix_unitaire / (duree_mois × 31) pour toutes les grilles';

    public function handle(): int
    {
        $dryRun   = $this->option('dry-run');
        $produits = Produit::with('grilles')->get();

        $this->info("🔍 {$produits->count()} produit(s) à recalculer...");
        $count = 0;

        foreach ($produits as $produit) {
            // Recalculer chaque durée existante
            foreach ($produit->grilles as $grille) {
                $miseCorrecte = $produit->prix_unitaire / ($grille->duree_mois * 31);
                $ancienne     = (float) $grille->montant_mise;

                if (abs($ancienne - $miseCorrecte) > 0.001) {
                    $count++;
                    if ($dryRun) {
                        $this->line(sprintf(
                            "  [dry-run] %s — %d mois : %.2f → %.2f",
                            $produit->nom, $grille->duree_mois, $ancienne, $miseCorrecte
                        ));
                    } else {
                        $grille->update(['montant_mise' => $miseCorrecte]);
                    }
                }
            }

            // Si pas de grilles du tout, créer 12 durées
            if ($produit->grilles->isEmpty()) {
                $count++;
                if ($dryRun) {
                    $this->line("  [dry-run] {$produit->nom} — création de 12 grilles");
                } else {
                    for ($i = 1; $i <= 12; $i++) {
                        $produit->grilles()->create([
                            'duree_mois'   => $i,
                            'montant_mise' => $produit->prix_unitaire / ($i * 31),
                        ]);
                    }
                    $this->info("  ✅ {$produit->nom} — 12 grilles créées");
                }
            }
        }

        $this->info($dryRun
            ? "📋 {$count} grille(s) seraient mises à jour."
            : "✅ {$count} grille(s) mises à jour.");

        return self::SUCCESS;
    }
}
