<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cotisation;
use App\Models\Tontine;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LivraisonController extends Controller
{
    /**
     * GET /api/livraisons/pret
     * Tontines prêtes à être livrées (statut = termine)
     */
    public function pret(Request $request): JsonResponse
    {
        $query = Tontine::with([
            'client:id,nom,prenom,telephone',
            'produits.produit:id,nom,code,prix_unitaire',
            'commercial:id,nom,prenom',
        ])->where('statut', 'termine')->orderBy('updated_at');

        // Recherche par nom de client
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('client', fn($q) =>
                $q->where('nom', 'ilike', "%$search%")
                  ->orWhere('prenom', 'ilike', "%$search%")
            );
        }

        $tontines = $query->paginate(20);

        // Enrichir chaque tontine avec le montant collecté
        $tontines->getCollection()->transform(function (Tontine $t) {
            $t->montant_collecte = (float) $t->cotisations()
                ->where('statut', 'valide')
                ->sum('montant_total');
            $t->total_mises_validees = $t->totalMisesValidees();
            $t->total_mises_attendues = $t->totalMisesAttendues();
            return $t;
        });

        return response()->json($tontines);
    }

    /**
     * GET /api/livraisons/historique
     * Tontines déjà livrées
     */
    public function historique(Request $request): JsonResponse
    {
        $query = Tontine::with([
            'client:id,nom,prenom,telephone',
            'produits.produit:id,nom,code',
            'commercial:id,nom,prenom',
            'livrePar:id,nom,prenom',
        ])->where('statut', 'livre')->orderByDesc('date_livraison');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('client', fn($q) =>
                $q->where('nom', 'ilike', "%$search%")
                  ->orWhere('prenom', 'ilike', "%$search%")
            );
        }

        if ($request->filled('date_debut')) {
            $query->whereDate('date_livraison', '>=', $request->date_debut);
        }
        if ($request->filled('date_fin')) {
            $query->whereDate('date_livraison', '<=', $request->date_fin);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * POST /api/livraisons
     * Valider la livraison d'une tontine — directeur uniquement
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tontine_id'      => 'required|uuid|exists:tontines,id',
            'notes_livraison' => 'nullable|string',
        ]);

        $tontine = Tontine::with(['client', 'commercial', 'produits.produit'])->findOrFail($data['tontine_id']);

        if ($tontine->statut !== 'termine') {
            return response()->json([
                'message'     => 'Cette tontine n\'est pas encore complète.',
                'progression' => $tontine->progression(),
            ], 422);
        }

        $tontine->update([
            'statut'          => 'livre',
            'date_livraison'  => now()->toDateString(),
            'livre_par_id'    => $request->user()->id,
            'notes_livraison' => $data['notes_livraison'] ?? null,
        ]);

        // Notifier le commercial
        $clientNom = trim(($tontine->client?->nom ?? '') . ' ' . ($tontine->client?->prenom ?? ''));
        if ($tontine->commercial_id) {
            NotificationService::envoyer(
                $tontine->commercial_id,
                'Livraison effectuée',
                "La tontine de $clientNom a été livrée.",
                'livraison'
            );
        }

        AuditService::log('livraison', 'Tontine', $tontine->id, null, [
            'date_livraison' => now()->toDateString(),
            'livre_par'      => $request->user()->id,
        ]);

        $tontine->load(['client:id,nom,prenom,telephone', 'produits.produit:id,nom,code', 'livrePar:id,nom,prenom']);
        $tontine->montant_collecte = (float) $tontine->cotisations()->where('statut', 'valide')->sum('montant_total');

        return response()->json([
            'message' => 'Livraison validée.',
            'tontine' => $tontine,
        ]);
    }

    /**
     * GET /api/livraisons/{tontine}/bon
     * Bon de livraison complet
     */
    public function bon(Request $request, Tontine $tontine): JsonResponse
    {
        $tontine->load([
            'client',
            'produits.produit:id,nom,code,prix_unitaire',
            'commercial:id,nom,prenom',
            'livrePar:id,nom,prenom',
        ]);

        $totalCollecte = (float) $tontine->cotisations()
            ->where('statut', 'valide')
            ->sum('montant_total');

        $totalMises = (int) $tontine->cotisations()
            ->where('statut', 'valide')
            ->sum('nombre_mises');

        $valeurProduits = $tontine->produits->sum(fn($l) => $l->prix_unitaire * $l->quantite);

        return response()->json([
            'tontine'          => $tontine,
            'total_mises'      => $totalMises,
            'total_attendues'  => $tontine->totalMisesAttendues(),
            'montant_collecte' => $totalCollecte,
            'valeur_produits'  => (float) $valeurProduits,
            'date_livraison'   => $tontine->date_livraison?->toDateString() ?? now()->toDateString(),
            'livre_par'        => $tontine->livrePar
                                    ? $tontine->livrePar->nom . ' ' . $tontine->livrePar->prenom
                                    : ($request->user()->nom . ' ' . $request->user()->prenom),
        ]);
    }
}
