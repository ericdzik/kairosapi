<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cotisation;
use App\Models\Tontine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PointageController extends Controller
{
    /**
     * GET /api/tontines/{tontine}/pointage
     * Grille avec étalement des mises sur les jours consécutifs
     */
    public function grille(Request $request, Tontine $tontine): JsonResponse
    {
        $user = $request->user();
        if ($user->isCommercial() && $tontine->commercial_id !== $user->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $tontine->load('produit:id,nom,prix_unitaire');

        $cotisations = Cotisation::where('tontine_id', $tontine->id)
            ->whereIn('statut', ['valide', 'en_attente'])
            ->orderBy('date_cotisation')
            ->get(['id', 'date_cotisation', 'nombre_mises', 'montant_total', 'statut']);

        // Construire la liste des jours cochés en étalant les mises
        // Valide = vert, en_attente = orange
        $joursValidesList   = [];
        $joursEnAttenteList = [];

        foreach ($cotisations as $cotisation) {
            $dateBase = $cotisation->date_cotisation->copy();
            for ($i = 0; $i < $cotisation->nombre_mises; $i++) {
                $dateStr = $dateBase->copy()->addDays($i)->toDateString();
                if ($cotisation->statut === 'valide') {
                    $joursValidesList[]   = $dateStr;
                } else {
                    $joursEnAttenteList[] = $dateStr;
                }
            }
        }

        $joursValidesCount   = array_count_values($joursValidesList);
        $joursEnAttenteCount = array_count_values($joursEnAttenteList);

        // Construire la grille
        $grille    = [];
        $dateDebut = $tontine->date_debut;

        for ($mois = 1; $mois <= $tontine->duree_mois; $mois++) {
            $moisData  = ['mois' => $mois, 'jours' => []];
            $debutMois = $dateDebut->copy()->addMonths($mois - 1);

            for ($jour = 1; $jour <= 30; $jour++) {
                $date       = $debutMois->copy()->addDays($jour - 1)->toDateString();
                $countValide    = $joursValidesCount[$date]   ?? 0;
                $countEnAttente = $joursEnAttenteCount[$date] ?? 0;

                $moisData['jours'][$jour] = [
                    'date'         => $date,
                    'coche'        => $countValide > 0,
                    'en_attente'   => $countValide === 0 && $countEnAttente > 0,
                    'nombre_mises' => $countValide + $countEnAttente,
                    'montant'      => ($countValide + $countEnAttente) > 0 ? (float) $tontine->montant_mise : 0,
                ];
            }
            $grille[] = $moisData;
        }

        $cotisationsValidees = $cotisations->where('statut', 'valide');

        return response()->json([
            'tontine'        => $tontine,
            'grille'         => $grille,
            'total_validees' => $cotisationsValidees->sum('nombre_mises'),
            'total_attendues'=> $tontine->totalMisesAttendues(),
        ]);
    }

    /**
     * GET /api/tontines/{tontine}/progression
     */
    public function progression(Request $request, Tontine $tontine): JsonResponse
    {
        $user = $request->user();
        if ($user->isCommercial() && $tontine->commercial_id !== $user->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $totalValidees  = $tontine->totalMisesValidees();
        $totalAttendues = $tontine->totalMisesAttendues();
        $montant        = $tontine->cotisations()->where('statut', 'valide')->sum('montant_total');
        $progression    = $tontine->progression();

        return response()->json([
            'tontine_id'       => $tontine->id,
            'client'           => $tontine->client?->only(['id', 'nom', 'prenom']),
            'statut'           => $tontine->statut,
            'total_validees'   => $totalValidees,
            'total_attendues'  => $totalAttendues,
            'mises_restantes'  => max(0, $totalAttendues - $totalValidees),
            'montant_collecte' => (float) $montant,
            'progression'      => $progression,
            'pret_a_livrer'    => $tontine->estPretALivrer(),
        ]);
    }
}
