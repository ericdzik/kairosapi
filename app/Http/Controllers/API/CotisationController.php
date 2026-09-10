<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cotisation;
use App\Models\Tontine;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\CotisationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CotisationController extends Controller
{
    public function __construct(private CotisationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Cotisation::with([
            'tontine.produits.produit:id,nom',
            'client:id,nom,prenom',
            'commercial:id,nom,prenom',
            'validateur:id,nom,prenom',
        ])->orderByDesc('date_cotisation');

        if ($user->isCommercial()) $query->where('commercial_id', $user->id);
        if ($request->filled('tontine_id'))   $query->where('tontine_id', $request->tontine_id);
        if ($request->filled('client_id'))    $query->where('client_id', $request->client_id);
        if ($request->filled('statut'))       $query->where('statut', $request->statut);
        if ($request->filled('date_debut'))   $query->whereDate('date_cotisation', '>=', $request->date_debut);
        if ($request->filled('date_fin'))     $query->whereDate('date_cotisation', '<=', $request->date_fin);

        return response()->json($query->paginate(50));
    }

    public function jour(Request $request): JsonResponse
    {
        $date  = $request->get('date', now()->toDateString());
        $user  = $request->user();

        $query = Cotisation::with(['client:id,nom,prenom', 'commercial:id,nom,prenom'])
            ->whereDate('date_cotisation', $date);

        if ($user->isCommercial()) $query->where('commercial_id', $user->id);

        $cotisations = $query->orderByDesc('created_at')->get();

        return response()->json([
            'date'          => $date,
            'total_mises'   => $cotisations->sum('nombre_mises'),
            'montant_total' => $cotisations->sum('montant_total'),
            'en_attente'    => $cotisations->where('statut', 'en_attente')->count(),
            'valide'        => $cotisations->where('statut', 'valide')->count(),
            'rejete'        => $cotisations->where('statut', 'rejete')->count(),
            'cotisations'   => $cotisations,
        ]);
    }

    public function parTontine(Request $request, Tontine $tontine): JsonResponse
    {
        $user = $request->user();
        if ($user->isCommercial() && $tontine->commercial_id !== $user->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        return response()->json(
            Cotisation::where('tontine_id', $tontine->id)
                ->with(['commercial:id,nom,prenom', 'validateur:id,nom,prenom'])
                ->orderByDesc('date_cotisation')
                ->paginate(50)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tontine_id'      => 'required|uuid|exists:tontines,id',
            'nombre_mises'    => 'required|integer|min:1|max:31',
            'montant_verse'   => 'nullable|numeric|min:1',
            'date_cotisation' => 'nullable|date',
        ]);

        $cotisation = $this->service->enregistrer($data, $request->user());
        $cotisation->load(['client:id,nom,prenom', 'commercial:id,nom,prenom']);

        // Notifier le bureau
        $clientNom    = $cotisation->client?->nom . ' ' . $cotisation->client?->prenom;
        $commercialNom= $cotisation->commercial?->nom . ' ' . $cotisation->commercial?->prenom;
        NotificationService::nouvelleMise($clientNom, $commercialNom, (float) $cotisation->montant_total);

        AuditService::log('create', 'Cotisation', $cotisation->id, null, $data);

        return response()->json($cotisation, 201);
    }

    public function valider(Request $request, Cotisation $cotisation): JsonResponse
    {
        $cotisation = $this->service->valider($cotisation, $request->user());
        $cotisation->load(['client:id,nom,prenom', 'validateur:id,nom,prenom']);

        // Notifier le commercial
        $clientNom = $cotisation->client?->nom . ' ' . $cotisation->client?->prenom;
        NotificationService::miseValidee($cotisation->commercial_id, $clientNom, (float) $cotisation->montant_total);

        AuditService::log('valider', 'Cotisation', $cotisation->id);
        return response()->json($cotisation);
    }

    public function validerLot(Request $request): JsonResponse
    {
        $data     = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'uuid']);
        $validees = $this->service->validerLot($data['ids'], $request->user());
        AuditService::log('valider_lot', 'Cotisation', null, null, ['ids' => $validees]);
        return response()->json(['message' => count($validees) . ' mise(s) validée(s).', 'validees' => $validees]);
    }

    public function rejeter(Request $request, Cotisation $cotisation): JsonResponse
    {
        $data       = $request->validate(['motif' => 'required|string|min:5']);
        $cotisation = $this->service->rejeter($cotisation, $data['motif'], $request->user());

        // Notifier le commercial
        $clientNom = $cotisation->client?->nom . ' ' . $cotisation->client?->prenom;
        NotificationService::miseRejetee($cotisation->commercial_id, $clientNom, $data['motif']);

        AuditService::log('rejeter', 'Cotisation', $cotisation->id, null, $data);
        return response()->json($cotisation);
    }

    public function annuler(Request $request, Cotisation $cotisation): JsonResponse
    {
        $cotisation = $this->service->annuler($cotisation, $request->user());
        AuditService::log('annuler', 'Cotisation', $cotisation->id);
        return response()->json(['message' => 'Mise annulée.', 'cotisation' => $cotisation]);
    }
}
