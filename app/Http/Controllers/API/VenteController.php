<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Produit;
use App\Models\Vente;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VenteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Vente::with(['client:id,nom,prenom', 'produit:id,nom,code', 'commercial:id,nom,prenom'])
            ->orderByDesc('date_vente');

        if ($user->isCommercial()) $query->where('commercial_id', $user->id);
        if ($request->filled('statut'))       $query->where('statut', $request->statut);
        if ($request->filled('client_id'))    $query->where('client_id', $request->client_id);
        if ($request->filled('date_debut'))   $query->whereDate('date_vente', '>=', $request->date_debut);
        if ($request->filled('date_fin'))     $query->whereDate('date_vente', '<=', $request->date_fin);

        return response()->json($query->paginate(50));
    }

    public function jour(Request $request): JsonResponse
    {
        $date  = $request->get('date', now()->toDateString());
        $user  = $request->user();

        $query = Vente::with(['client:id,nom,prenom', 'produit:id,nom'])
            ->whereDate('date_vente', $date);

        if ($user->isCommercial()) $query->where('commercial_id', $user->id);

        $ventes = $query->get();

        return response()->json([
            'date'          => $date,
            'total_ventes'  => $ventes->count(),
            'montant_total' => $ventes->sum('montant'),
            'en_attente'    => $ventes->where('statut', 'en_attente')->count(),
            'valide'        => $ventes->where('statut', 'valide')->count(),
            'ventes'        => $ventes,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'client_id'        => 'nullable|uuid|exists:clients,id',
            'client_nom'       => 'nullable|string|max:200',
            'client_telephone' => 'nullable|string|max:20',
            'produits'         => 'required|array|min:1',
            'produits.*.produit_id' => 'required|uuid|exists:produits,id',
            'produits.*.quantite'   => 'required|integer|min:1',
            'date_vente'       => 'nullable|date',
            'notes'            => 'nullable|string',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',
            'adresse_complete' => 'nullable|string|max:500',
        ]);

        if (empty($data['client_id']) && empty($data['client_nom'])) {
            return response()->json(['message' => 'Fournissez un client enregistré ou un nom.'], 422);
        }

        if (!empty($data['client_id']) && $user->isCommercial()) {
            $client = \App\Models\Client::findOrFail($data['client_id']);
            if ($client->commercial_id !== $user->id) {
                return response()->json(['message' => 'Ce client ne vous appartient pas.'], 403);
            }
        }

        // Calculer le montant total
        $montantTotal = 0;
        foreach ($data['produits'] as $ligne) {
            $produit = Produit::findOrFail($ligne['produit_id']);
            $prix    = $produit->prix_vente_directe ?? $produit->prix_unitaire;
            $montantTotal += $prix * $ligne['quantite'];
        }

        // Créer une vente par produit
        $ventes = [];
        foreach ($data['produits'] as $ligne) {
            $produit = Produit::findOrFail($ligne['produit_id']);
            $prix    = $produit->prix_vente_directe ?? $produit->prix_unitaire;

            $vente = Vente::create([
                'client_id'        => $data['client_id'] ?? null,
                'client_nom'       => $data['client_nom'] ?? null,
                'client_telephone' => $data['client_telephone'] ?? null,
                'produit_id'       => $produit->id,
                'commercial_id'    => $user->id,
                'quantite'         => $ligne['quantite'],
                'montant'          => $prix * $ligne['quantite'],
                'date_vente'       => $data['date_vente'] ?? now()->toDateString(),
                'statut'           => 'en_attente',
                'notes'            => $data['notes'] ?? null,
                'latitude'         => $data['latitude'] ?? null,
                'longitude'        => $data['longitude'] ?? null,
                'adresse_complete' => $data['adresse_complete'] ?? null,
            ]);
            $ventes[] = $vente;
        }

        AuditService::log('create', 'Vente', $ventes[0]->id);

        return response()->json([
            'message' => count($ventes) . ' vente(s) enregistrée(s).',
            'montant_total' => $montantTotal,
            'ventes' => $ventes,
        ], 201);
    }

    public function show(Vente $vente): JsonResponse
    {
        $vente->load(['client', 'produit', 'commercial:id,nom,prenom', 'validateur:id,nom,prenom']);
        return response()->json($vente);
    }

    public function valider(Request $request, Vente $vente): JsonResponse
    {
        if ($vente->statut !== 'en_attente') {
            return response()->json(['message' => 'Seules les ventes en attente peuvent être validées.'], 422);
        }

        $vente->update([
            'statut'        => 'valide',
            'validateur_id' => $request->user()->id,
            'valide_at'     => now(),
        ]);

        AuditService::log('valider', 'Vente', $vente->id);
        return response()->json($vente->fresh());
    }

    public function annuler(Request $request, Vente $vente): JsonResponse
    {
        $data = $request->validate(['motif' => 'required|string|min:5']);

        if ($vente->statut === 'valide') {
            return response()->json(['message' => 'Impossible d\'annuler une vente validée.'], 422);
        }

        $vente->update(['statut' => 'annule', 'motif_annulation' => $data['motif']]);
        AuditService::log('annuler', 'Vente', $vente->id, null, $data);

        return response()->json(['message' => 'Vente annulée.']);
    }
}
