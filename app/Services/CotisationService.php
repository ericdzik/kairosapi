<?php

namespace App\Services;

use App\Models\Cotisation;
use App\Models\Tontine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CotisationService
{
    public function enregistrer(array $data, User $commercial): Cotisation
    {
        $tontine = Tontine::with('produit')->findOrFail($data['tontine_id']);

        // Seul le commercial peut enregistrer des mises
        if (!$commercial->isCommercial()) {
            throw ValidationException::withMessages([
                'role' => ['Seul un commercial peut enregistrer des mises.'],
            ])->status(403);
        }

        if ($tontine->commercial_id !== $commercial->id) {
            throw ValidationException::withMessages([
                'tontine_id' => ['Cette tontine ne vous appartient pas.'],
            ])->status(403);
        }

        if ($tontine->statut !== 'en_cours') {
            throw ValidationException::withMessages([
                'tontine_id' => ['Cette tontine n\'est pas active.'],
            ]);
        }

        // Bloquer si la cotisation est déjà complète (100%)
        if ($tontine->estPretALivrer()) {
            throw ValidationException::withMessages([
                'tontine_id' => ['Cette tontine a atteint son objectif. Aucune mise supplémentaire n\'est acceptée.'],
            ]);
        }

        // Utiliser le montant_mise de la tontine (pas le prix du produit)
        $montantMise  = (float) $tontine->montant_mise;
        $nombreMises  = $data['nombre_mises'] ?? 1;
        $montantVerse = isset($data['montant_verse']) ? (float) $data['montant_verse'] : null;
        $montantTotal = $montantMise * $nombreMises;

        // Valider que le montant versé ne dépasse pas le montant total de la cotisation
        if ($montantVerse !== null && $montantVerse > $montantTotal) {
            throw ValidationException::withMessages([
                'montant_verse' => ['Le montant versé ne peut pas dépasser le montant total (' . $montantTotal . ' FCFA).'],
            ]);
        }

        // Vérifier que la cotisation ne fait pas dépasser le montant prévu de la tontine
        $montantPrevu   = $tontine->montantTotalAttendu();
        $montantDejaVerse = (float) $tontine->cotisations()
            ->whereIn('statut', ['en_attente', 'valide'])
            ->selectRaw('SUM(COALESCE(montant_verse, montant_total)) as total')
            ->value('total');
        $montantEffectif = $montantVerse ?? $montantTotal;

        if (($montantDejaVerse + $montantEffectif) > $montantPrevu) {
            $restant = $montantPrevu - $montantDejaVerse;
            throw ValidationException::withMessages([
                'nombre_mises' => [
                    'Cette mise dépasse le montant prévu de la tontine. '
                    . 'Il reste ' . number_format($restant, 0, ',', ' ') . ' FCFA à couvrir '
                    . '(max ' . floor($restant / $montantMise) . ' mise(s)).'
                ],
            ]);
        }

        return Cotisation::create([
            'tontine_id'       => $tontine->id,
            'client_id'        => $tontine->client_id,
            'commercial_id'    => $tontine->commercial_id,
            'nombre_mises'     => $nombreMises,
            'montant_unitaire' => $montantMise,
            'montant_total'    => $montantTotal,
            'montant_verse'    => $montantVerse, // null = mise complète
            'date_cotisation'  => $data['date_cotisation'] ?? now()->toDateString(),
            'statut'           => 'en_attente',
        ]);
    }

    public function valider(Cotisation $cotisation, User $validateur): Cotisation
    {
        if (!$validateur->isDirecteur()) {
            throw ValidationException::withMessages([
                'role' => ['Seul le directeur peut valider une mise.'],
            ])->status(403);
        }

        if ($cotisation->statut !== 'en_attente') {
            throw ValidationException::withMessages([
                'statut' => ['Seules les mises en attente peuvent être validées.'],
            ]);
        }

        $cotisation->update([
            'statut'        => 'valide',
            'validateur_id' => $validateur->id,
            'valide_at'     => now(),
        ]);

        $this->verifierCompletion($cotisation->tontine_id);

        return $cotisation->fresh();
    }

    public function validerLot(array $ids, User $validateur): array
    {
        if (!$validateur->isDirecteur()) {
            throw ValidationException::withMessages([
                'role' => ['Seul le directeur peut valider des mises.'],
            ])->status(403);
        }

        $cotisations = Cotisation::whereIn('id', $ids)->where('statut', 'en_attente')->get();
        $validees = [];

        DB::transaction(function () use ($cotisations, $validateur, &$validees) {
            foreach ($cotisations as $c) {
                $c->update([
                    'statut'        => 'valide',
                    'validateur_id' => $validateur->id,
                    'valide_at'     => now(),
                ]);
                $validees[] = $c->id;
            }
        });

        foreach ($cotisations->pluck('tontine_id')->unique() as $tontineId) {
            $this->verifierCompletion($tontineId);
        }

        return $validees;
    }

    public function rejeter(Cotisation $cotisation, string $motif, User $validateur): Cotisation
    {
        if (!$validateur->isDirecteur()) {
            throw ValidationException::withMessages([
                'role' => ['Seul le directeur peut rejeter une mise.'],
            ])->status(403);
        }

        if ($cotisation->statut !== 'en_attente') {
            throw ValidationException::withMessages([
                'statut' => ['Seules les mises en attente peuvent être rejetées.'],
            ]);
        }

        $cotisation->update([
            'statut'        => 'rejete',
            'motif_rejet'   => $motif,
            'validateur_id' => $validateur->id,
        ]);

        return $cotisation->fresh();
    }

    public function annuler(Cotisation $cotisation, User $user): Cotisation
    {
        if ($cotisation->statut !== 'en_attente') {
            throw ValidationException::withMessages([
                'statut' => ['Impossible d\'annuler une mise déjà traitée.'],
            ]);
        }

        if ($user->isCommercial() && $cotisation->commercial_id !== $user->id) {
            throw ValidationException::withMessages(['id' => ['Accès refusé.']])->status(403);
        }

        $cotisation->update(['statut' => 'annule']);
        return $cotisation->fresh();
    }

    private function verifierCompletion(string $tontineId): void
    {
        $tontine = Tontine::with('client')->find($tontineId);
        if (!$tontine || $tontine->statut !== 'en_cours') return;

        if ($tontine->estPretALivrer()) {
            $tontine->update(['statut' => 'termine']);
            // Notifier
            $clientNom = $tontine->client?->nom . ' ' . $tontine->client?->prenom;
            \App\Services\NotificationService::tontineTerminee($clientNom, $tontine->commercial_id);
        }
    }
}
