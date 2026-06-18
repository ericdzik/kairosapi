<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cotisation;
use App\Models\Tontine;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LivraisonController extends Controller
{
    /**
     * GET /api/livraisons/pret
     * Tontines prêtes à être livrées (100%)
     */
    public function pret(): JsonResponse
    {
        $tontines = Tontine::with(['client:id,nom,prenom,telephone', 'produits.produit:id,nom,code', 'commercial:id,nom,prenom'])
            ->where('statut', 'termine')
            ->orderBy('updated_at')
            ->paginate(20);

        return response()->json($tontines);
    }

    /**
     * POST /api/livraisons
     * Valider la livraison d'une tontine
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tontine_id' => 'required|uuid|exists:tontines,id',
            'notes'      => 'nullable|string',
        ]);

        $tontine = Tontine::findOrFail($data['tontine_id']);

        if ($tontine->statut !== 'termine') {
            return response()->json([
                'message'    => 'Cette tontine n\'est pas encore complète.',
                'progression'=> $tontine->progression(),
            ], 422);
        }

        $tontine->update(['statut' => 'livre']);

        AuditService::log('livraison', 'Tontine', $tontine->id, null, [
            'date_livraison' => now()->toDateString(),
        ]);

        $tontine->load(['client:id,nom,prenom', 'produit:id,nom,code']);

        return response()->json([
            'message' => 'Livraison validée.',
            'tontine' => $tontine,
        ]);
    }

    /**
     * GET /api/livraisons/{livraison}/bon
     */
    public function bon(Request $request, Tontine $tontine): JsonResponse
    {
        $tontine->load(['client', 'produit', 'commercial:id,nom,prenom']);

        $totalCollecte = Cotisation::where('tontine_id', $tontine->id)
            ->where('statut', 'valide')
            ->sum('montant_total');

        $totalMises = Cotisation::where('tontine_id', $tontine->id)
            ->where('statut', 'valide')
            ->sum('nombre_mises');

        return response()->json([
            'tontine'         => $tontine,
            'total_mises'     => (int) $totalMises,
            'montant_collecte'=> (float) $totalCollecte,
            'date_livraison'  => now()->toDateString(),
            'valide_par'      => $request->user()->nom . ' ' . $request->user()->prenom,
        ]);
    }
}
