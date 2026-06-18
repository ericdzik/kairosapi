<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Cotisation;
use App\Models\Tontine;
use App\Models\Vente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function directeur(): JsonResponse
    {
        return response()->json([
            'clients' => [
                'total'    => Client::count(),
            ],
            'tontines' => [
                'total'    => Tontine::count(),
                'actives'  => Tontine::where('statut', 'en_cours')->count(),
                'terminees'=> Tontine::where('statut', 'termine')->count(),
                'livrees'  => Tontine::where('statut', 'livre')->count(),
            ],
            'finances' => [
                'cotisations_total'  => (float) Cotisation::where('statut', 'valide')->sum('montant_total'),
                'ventes_total'       => (float) Vente::where('statut', 'valide')->sum('montant'),
                'mises_en_attente'   => Cotisation::where('statut', 'en_attente')->count(),
                'ventes_en_attente'  => Vente::where('statut', 'en_attente')->count(),
            ],
            'top_commerciaux' => DB::table('cotisations')
                ->join('users', 'cotisations.commercial_id', '=', 'users.id')
                ->where('cotisations.statut', 'valide')
                ->whereNull('cotisations.deleted_at')
                ->groupBy('users.id', 'users.nom', 'users.prenom')
                ->orderByDesc('montant')
                ->limit(5)
                ->select(
                    'users.id',
                    DB::raw("users.nom || ' ' || users.prenom as nom_complet"),
                    DB::raw('SUM(cotisations.montant_total) as montant')
                )
                ->get(),
        ]);
    }

    public function comptabilite(): JsonResponse
    {
        $aujourd_hui = today()->toDateString();
        $misesJour   = Cotisation::whereDate('date_cotisation', $aujourd_hui)->get();
        $ventesJour  = Vente::whereDate('date_vente', $aujourd_hui)->get();

        return response()->json([
            'aujourd_hui' => [
                'date'               => $aujourd_hui,
                'mises_total'        => $misesJour->sum('nombre_mises'),
                'mises_montant'      => $misesJour->where('statut', 'valide')->sum('montant_total'),
                'mises_en_attente'   => $misesJour->where('statut', 'en_attente')->count(),
                'ventes_montant'     => $ventesJour->where('statut', 'valide')->sum('montant'),
                'ventes_en_attente'  => $ventesJour->where('statut', 'en_attente')->count(),
            ],
            'historique_7j' => DB::table('cotisations')
                ->whereNull('deleted_at')
                ->where('statut', 'valide')
                ->where('date_cotisation', '>=', now()->subDays(7)->toDateString())
                ->groupBy('date_cotisation')
                ->orderBy('date_cotisation')
                ->select('date_cotisation', DB::raw('SUM(montant_total) as montant'), DB::raw('SUM(nombre_mises) as nb_mises'))
                ->get(),
        ]);
    }

    public function commercial(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'commercial'      => ['id' => $user->id, 'nom' => $user->nom, 'prenom' => $user->prenom],
            'clients'         => ['total' => Client::where('commercial_id', $user->id)->count()],
            'tontines'        => [
                'actives'  => Tontine::where('commercial_id', $user->id)->where('statut', 'en_cours')->count(),
                'terminees'=> Tontine::where('commercial_id', $user->id)->where('statut', 'termine')->count(),
            ],
            'cotisations_collecte' => (float) Cotisation::where('commercial_id', $user->id)->where('statut', 'valide')->sum('montant_total'),
            'ventes_collecte'      => (float) Vente::where('commercial_id', $user->id)->where('statut', 'valide')->sum('montant'),
            'mises_en_attente'     => Cotisation::where('commercial_id', $user->id)->where('statut', 'en_attente')->count(),
        ]);
    }

    public function controleur(): JsonResponse
    {
        return response()->json([
            'mises_en_attente_24h' => [
                'count'       => Cotisation::where('statut', 'en_attente')->where('created_at', '<', now()->subHours(24))->count(),
                'cotisations' => Cotisation::with(['client:id,nom,prenom', 'commercial:id,nom,prenom'])
                    ->where('statut', 'en_attente')->where('created_at', '<', now()->subHours(24))->get(),
            ],
            'rejets_7j' => [
                'count'       => Cotisation::where('statut', 'rejete')->where('updated_at', '>=', now()->subDays(7))->count(),
                'cotisations' => Cotisation::with(['client:id,nom,prenom'])
                    ->where('statut', 'rejete')->where('updated_at', '>=', now()->subDays(7))->orderByDesc('updated_at')->get(),
            ],
        ]);
    }

    /**
     * GET /api/dashboard/stats?periode=jour|semaine|mois|annee
     */
    public function stats(Request $request): JsonResponse
    {
        $periode = $request->get('periode', 'semaine');
        $user    = $request->user();

        [$format, $debut, $groupBy] = match ($periode) {
            'jour'   => ['H:00', now()->startOfDay(),   "date_trunc('hour', created_at)"],
            'semaine'=> ['D',    now()->startOfWeek(),  "date_trunc('day', created_at)"],
            'mois'   => ['d/m',  now()->startOfMonth(), "date_trunc('day', created_at)"],
            'annee'  => ['M Y',  now()->startOfYear(),  "date_trunc('month', created_at)"],
            default  => ['D',    now()->startOfWeek(),  "date_trunc('day', created_at)"],
        };

        $cotisations = \DB::table('cotisations')
            ->whereNull('deleted_at')
            ->where('statut', 'valide')
            ->where('created_at', '>=', $debut)
            ->when($user->isCommercial(), fn($q) => $q->where('commercial_id', $user->id))
            ->selectRaw("$groupBy as periode, SUM(montant_total) as montant, COUNT(*) as nb")
            ->groupByRaw($groupBy)
            ->orderByRaw($groupBy)
            ->get();

        $ventes = \DB::table('ventes')
            ->whereNull('deleted_at')
            ->where('statut', 'valide')
            ->where('created_at', '>=', $debut)
            ->when($user->isCommercial(), fn($q) => $q->where('commercial_id', $user->id))
            ->selectRaw("$groupBy as periode, SUM(montant) as montant, COUNT(*) as nb")
            ->groupByRaw($groupBy)
            ->orderByRaw($groupBy)
            ->get();

        return response()->json([
            'periode'     => $periode,
            'cotisations' => $cotisations,
            'ventes'      => $ventes,
        ]);
    }

    /**
     * GET /api/commerciaux/stats?date_debut=&date_fin=&commercial_id=
     */
    public function commerciauxStats(Request $request): JsonResponse
    {
        $dateDebut    = $request->get('date_debut');
        $dateFin      = $request->get('date_fin');
        $commercialId = $request->get('commercial_id');

        $query = \App\Models\User::where('role', 'commercial')->where('actif', true);
        if ($commercialId) {
            $query->where('id', $commercialId);
        }
        $commerciaux = $query->get(['id', 'nom', 'prenom', 'telephone']);

        $stats = $commerciaux->map(function ($commercial) use ($dateDebut, $dateFin) {
            $cotisQ = \App\Models\Cotisation::where('commercial_id', $commercial->id)->where('statut', 'valide');
            $venteQ = \App\Models\Vente::where('commercial_id', $commercial->id)->where('statut', 'valide');

            if ($dateDebut) {
                $cotisQ->whereDate('created_at', '>=', $dateDebut);
                $venteQ->whereDate('created_at', '>=', $dateDebut);
            }
            if ($dateFin) {
                $cotisQ->whereDate('created_at', '<=', $dateFin);
                $venteQ->whereDate('created_at', '<=', $dateFin);
            }

            return [
                'id'                  => $commercial->id,
                'nom'                 => $commercial->nom,
                'prenom'              => $commercial->prenom,
                'telephone'           => $commercial->telephone,
                'nb_clients'          => \App\Models\Client::where('commercial_id', $commercial->id)->count(),
                'nb_tontines'         => \App\Models\Tontine::where('commercial_id', $commercial->id)->count(),
                'tontines_actives'    => \App\Models\Tontine::where('commercial_id', $commercial->id)->where('statut', 'en_cours')->count(),
                'tontines_terminees'  => \App\Models\Tontine::where('commercial_id', $commercial->id)->where('statut', 'termine')->count(),
                'tontines_livrees'    => \App\Models\Tontine::where('commercial_id', $commercial->id)->where('statut', 'livre')->count(),
                'montant_cotisations' => (float) $cotisQ->sum('montant_total'),
                'nb_ventes'           => $venteQ->count(),
                'montant_ventes'      => (float) $venteQ->sum('montant'),
                'mises_en_attente'    => \App\Models\Cotisation::where('commercial_id', $commercial->id)->where('statut', 'en_attente')->count(),
            ];
        });

        return response()->json($stats->sortByDesc(fn($c) => $c['montant_cotisations'] + $c['montant_ventes'])->values());
    }
}
