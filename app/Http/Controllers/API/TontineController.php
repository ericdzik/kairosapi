<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Tontine;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TontineController extends Controller
{
    /**
     * GET /api/tontines
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Tontine::with([
                'client:id,nom,prenom,telephone',
                'produits.produit:id,nom,code,prix_unitaire,image',
                'commercial:id,nom,prenom',
            ])->orderByDesc('created_at');

        if ($user->isCommercial()) {
            $query->where('commercial_id', $user->id);
        }

        if ($request->filled('statut'))    $query->where('statut', $request->statut);
        if ($request->filled('client_id')) $query->where('client_id', $request->client_id);

        $tontines = $query->paginate(50);

        // Ajouter image_url sur chaque produit des lignes + montants pour le formulaire de cotisation
        $tontines->getCollection()->transform(function ($t) {
            foreach ($t->produits as $ligne) {
                if ($ligne->produit && $ligne->produit->image) {
                    $ligne->produit->image_url = \Illuminate\Support\Facades\Storage::disk('public')->url($ligne->produit->image);
                }
            }
            $t->montant_total_attendu = $t->montantTotalAttendu();
            $t->valeur_produits       = $t->valeurTotaleProduits();
            $t->montant_total_verse   = $t->cotisations()
                ->whereIn('statut', ['en_attente', 'valide'])
                ->selectRaw('SUM(COALESCE(montant_verse, montant_total)) as total')
                ->value('total') ?? 0;
            return $t;
        });

        return response()->json($tontines);
    }

    /**
     * GET /api/clients/{client}/tontines
     */
    public function parClient(Request $request, Client $client): JsonResponse
    {
        $user = $request->user();
        if ($user->isCommercial() && $client->commercial_id !== $user->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $tontines = Tontine::where('client_id', $client->id)
            ->with(['produit:id,nom,prix_unitaire', 'commercial:id,nom,prenom'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($tontines);
    }

    /**
     * POST /api/tontines
     * Inscrire un client à une tontine
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'client_id'    => 'required|uuid|exists:clients,id',
            'duree_mois'   => 'required|integer|min:1|max:12',
            'date_debut'   => 'required|date',
            'notes'        => 'nullable|string',
            'produits'     => 'required|array|min:1',
            'produits.*.produit_id' => 'required|uuid|exists:produits,id',
            'produits.*.quantite'   => 'required|integer|min:1',
        ]);

        $client = \App\Models\Client::findOrFail($data['client_id']);
        if ($user->isCommercial() && $client->commercial_id !== $user->id) {
            return response()->json(['message' => 'Ce client ne vous appartient pas.'], 403);
        }

        // Calculer le montant_mise depuis les grilles des produits selon la durée choisie
        $montantMise = 0;
        $lignes = [];
        foreach ($data['produits'] as $ligne) {
            $produit  = \App\Models\Produit::with('grilles')->findOrFail($ligne['produit_id']);
            $quantite = $ligne['quantite'];

            // Chercher la mise dans la grille pour la durée choisie
            // Si pas de grille, calculer prix / (duree_mois * 31) sans arrondi
            $grille      = $produit->grilles->firstWhere('duree_mois', $data['duree_mois']);
            $miseProduit = $grille
                ? (float) $grille->montant_mise
                : ($produit->prix_unitaire / ($data['duree_mois'] * 31));

            $sousTotal   = $miseProduit * $quantite;
            $montantMise += $sousTotal;

            $lignes[] = [
                'produit_id'    => $produit->id,
                'quantite'      => $quantite,
                'prix_unitaire' => $produit->prix_unitaire,
                'sous_total'    => $produit->prix_unitaire * $quantite,
            ];
        }

        $tontine = \App\Models\Tontine::create([
            'client_id'    => $data['client_id'],
            'produit_id'   => $lignes[0]['produit_id'],
            'commercial_id'=> $client->commercial_id, // utiliser le commercial du client
            'duree_mois'   => $data['duree_mois'],
            'montant_mise' => $montantMise,
            'date_debut'   => $data['date_debut'],
            'notes'        => $data['notes'] ?? null,
        ]);

        // Enregistrer les lignes produits
        foreach ($lignes as $ligne) {
            $tontine->produits()->create($ligne);
        }

        $tontine->load(['client:id,nom,prenom', 'produits.produit:id,nom,prix_unitaire']);

        AuditService::log('create', 'Tontine', $tontine->id);

        return response()->json($tontine, 201);
    }

    /**
     * GET /api/tontines/{tontine}
     */
    public function show(Tontine $tontine): JsonResponse
    {
        $tontine->load(['client:id,nom,prenom,telephone', 'produits.produit', 'commercial:id,nom,prenom']);

        // Ajouter image_url
        foreach ($tontine->produits as $ligne) {
            if ($ligne->produit && $ligne->produit->image) {
                $ligne->produit->image_url = \Illuminate\Support\Facades\Storage::disk('public')->url($ligne->produit->image);
            }
        }

        return response()->json([
            'tontine'          => $tontine,
            'total_validees'   => $tontine->totalMisesValidees(),
            'total_attendues'  => $tontine->totalMisesAttendues(),
            'mises_restantes'  => max(0, $tontine->totalMisesAttendues() - $tontine->totalMisesValidees()),
            'montant_collecte' => $tontine->montantTotalVerse(),
            'montant_attendu'  => $tontine->montantTotalAttendu(),
            'valeur_produits'  => $tontine->valeurTotaleProduits(),
            'progression'      => $tontine->progression(),
            'pret_a_livrer'    => $tontine->estPretALivrer(),
        ]);
    }

    /**
     * PATCH /api/tontines/{tontine}/suspendre
     */
    public function suspendre(Tontine $tontine): JsonResponse
    {
        $nouveau = $tontine->statut === 'suspendu' ? 'en_cours' : 'suspendu';
        $tontine->update(['statut' => $nouveau]);
        AuditService::log('suspendre', 'Tontine', $tontine->id);
        return response()->json(['statut' => $nouveau]);
    }
}
