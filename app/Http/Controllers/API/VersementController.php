<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cotisation;
use App\Models\User;
use App\Models\Vente;
use App\Models\Versement;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VersementController extends Controller
{
    /**
     * GET /api/rapport-journalier?date=YYYY-MM-DD
     * Rapport complet du jour groupé par commercial (cotisations + ventes)
     */
    public function rapport(Request $request): JsonResponse
    {
        $date = $request->get('date', now()->toDateString());

        // Tous les commerciaux actifs
        $commerciaux = User::where('role', 'commercial')
            ->where('actif', true)
            ->get(['id', 'nom', 'prenom', 'telephone']);

        $rapport = $commerciaux->map(function (User $commercial) use ($date) {
            $cotisations = Cotisation::with(['client:id,nom,prenom', 'tontine:id,montant_mise'])
                ->where('commercial_id', $commercial->id)
                ->whereDate('date_cotisation', $date)
                ->get();

            $ventes = Vente::with(['client:id,nom,prenom'])
                ->where('commercial_id', $commercial->id)
                ->whereDate('date_vente', $date)
                ->get();

            $montantAttendu = $cotisations->whereIn('statut', ['en_attente', 'valide'])->sum('montant_total')
                + $ventes->whereIn('statut', ['en_attente', 'valide'])->sum('montant');

            $versement = Versement::where('commercial_id', $commercial->id)
                ->whereDate('date', $date)
                ->first();

            return [
                'commercial'       => $commercial,
                'nb_mises'         => $cotisations->count(),
                'montant_mises'    => (float) $cotisations->whereIn('statut', ['en_attente', 'valide'])->sum('montant_total'),
                'mises_en_attente' => $cotisations->where('statut', 'en_attente')->count(),
                'nb_ventes'        => $ventes->count(),
                'montant_ventes'   => (float) $ventes->whereIn('statut', ['en_attente', 'valide'])->sum('montant'),
                'montant_attendu'  => (float) $montantAttendu,
                'cotisations'      => $cotisations->values(),
                'ventes'           => $ventes->values(),
                'versement'        => $versement,
            ];
        })->filter(fn($c) => $c['nb_mises'] > 0 || $c['nb_ventes'] > 0)->values();

        $totalAttendu = $rapport->sum('montant_attendu');
        $totalVerse   = Versement::whereDate('date', $date)->sum('montant_verse');

        return response()->json([
            'date'           => $date,
            'total_attendu'  => (float) $totalAttendu,
            'total_verse'    => (float) $totalVerse,
            'ecart_global'   => (float) ($totalVerse - $totalAttendu),
            'nb_commerciaux' => $rapport->count(),
            'commerciaux'    => $rapport,
        ]);
    }

    /**
     * POST /api/versements
     * Enregistrer le versement d'un commercial pour une date donnée
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'commercial_id'  => 'required|uuid|exists:users,id',
            'date'           => 'required|date',
            'montant_verse'  => 'required|numeric|min:0',
            'notes'          => 'nullable|string',
        ]);

        // Vérifier que c'est bien un commercial
        $commercial = User::findOrFail($data['commercial_id']);
        if (!$commercial->isCommercial()) {
            return response()->json(['message' => 'L\'utilisateur sélectionné n\'est pas un commercial.'], 422);
        }

        // Calculer le montant attendu du jour
        $montantAttendu = Cotisation::where('commercial_id', $data['commercial_id'])
            ->whereDate('date_cotisation', $data['date'])
            ->whereIn('statut', ['en_attente', 'valide'])
            ->sum('montant_total')
            + Vente::where('commercial_id', $data['commercial_id'])
            ->whereDate('date_vente', $data['date'])
            ->whereIn('statut', ['en_attente', 'valide'])
            ->sum('montant');

        $ecart = $data['montant_verse'] - $montantAttendu;
        $statut = match (true) {
            $ecart == 0  => 'conforme',
            $ecart > 0   => 'ecart_positif',
            default      => 'ecart_negatif',
        };

        $versement = Versement::updateOrCreate(
            ['commercial_id' => $data['commercial_id'], 'date' => $data['date']],
            [
                'secretaire_id'  => $request->user()->id,
                'montant_attendu'=> $montantAttendu,
                'montant_verse'  => $data['montant_verse'],
                'ecart'          => $ecart,
                'statut'         => $statut,
                'notes'          => $data['notes'] ?? null,
            ]
        );

        // Notifier le directeur si écart
        if ($statut !== 'conforme') {
            $commercialNom = $commercial->nom . ' ' . $commercial->prenom;
            $signe = $ecart > 0 ? '+' : '';
            NotificationService::notifierRole(
                'directeur',
                'Écart de caisse détecté',
                "Versement de $commercialNom le {$data['date']} : écart de {$signe}{$ecart} FCFA.",
                'alerte'
            );
        }

        AuditService::log('versement', 'Versement', $versement->id, null, [
            'commercial_id'  => $data['commercial_id'],
            'date'           => $data['date'],
            'montant_attendu'=> $montantAttendu,
            'montant_verse'  => $data['montant_verse'],
            'ecart'          => $ecart,
            'statut'         => $statut,
        ]);

        $versement->load(['commercial:id,nom,prenom', 'secretaire:id,nom,prenom']);

        return response()->json([
            'message'         => 'Versement enregistré.',
            'versement'       => $versement,
            'montant_attendu' => (float) $montantAttendu,
            'ecart'           => (float) $ecart,
            'statut'          => $statut,
        ], 201);
    }

    /**
     * GET /api/versements?date=&commercial_id=
     * Historique des versements
     */
    public function index(Request $request): JsonResponse
    {
        $query = Versement::with(['commercial:id,nom,prenom', 'secretaire:id,nom,prenom'])
            ->orderByDesc('date');

        if ($request->filled('date'))          $query->whereDate('date', $request->date);
        if ($request->filled('commercial_id')) $query->where('commercial_id', $request->commercial_id);
        if ($request->filled('statut'))        $query->where('statut', $request->statut);

        return response()->json($query->paginate(30));
    }
}
