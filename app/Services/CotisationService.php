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

        // Seuls les commerciaux et directeurs peuvent enregistrer des mises
        if ($commercial->role === 'secretaire' || $commercial->role === 'controleur') {
            throw ValidationException::withMessages([
                'role' => ['Vous n\'êtes pas autorisé à enregistrer des mises.'],
            ])->status(403);
        }

        if ($commercial->isCommercial() && $tontine->commercial_id !== $commercial->id) {
            throw ValidationException::withMessages([
                'tontine_id' => ['Cette tontine ne vous appartient pas.'],
            ])->status(403);
        }

        if ($tontine->statut !== 'en_cours') {
            throw ValidationException::withMessages([
                'tontine_id' => ['Cette tontine n\'est pas active.'],
            ]);
        }

        // Utiliser le montant_mise de la tontine (pas le prix du produit)
        $montantMise = $tontine->montant_mise;
        $nombreMises = $data['nombre_mises'] ?? 1;

        return Cotisation::create([
            'tontine_id'       => $tontine->id,
            'client_id'        => $tontine->client_id,
            'commercial_id'    => $tontine->commercial_id, // commercial de la tontine
            'nombre_mises'     => $nombreMises,
            'montant_unitaire' => $montantMise,
            'montant_total'    => $montantMise * $nombreMises,
            'date_cotisation'  => $data['date_cotisation'] ?? now()->toDateString(),
            'statut'           => 'en_attente',
        ]);
    }

    public function valider(Cotisation $cotisation, User $validateur): Cotisation
    {
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
