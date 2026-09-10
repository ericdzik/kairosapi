<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cotisation;
use App\Models\Depense;
use App\Models\Vente;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepenseController extends Controller
{
    /**
     * GET /api/depenses
     * Liste paginée avec filtres + résumé CA/bénéfice
     */
    public function index(Request $request): JsonResponse
    {
        $query = Depense::with('createdBy:id,nom,prenom')
            ->orderByDesc('date_depense');

        if ($request->filled('categorie'))  $query->where('categorie', $request->categorie);
        if ($request->filled('date_debut')) $query->whereDate('date_depense', '>=', $request->date_debut);
        if ($request->filled('date_fin'))   $query->whereDate('date_depense', '<=', $request->date_fin);

        // Mois courant par défaut pour le résumé
        $moisDebut = $request->get('date_debut', now()->startOfMonth()->toDateString());
        $moisFin   = $request->get('date_fin',   now()->endOfMonth()->toDateString());

        // CA de la période
        $ca = (float) Cotisation::where('statut', 'valide')
            ->whereDate('date_cotisation', '>=', $moisDebut)
            ->whereDate('date_cotisation', '<=', $moisFin)
            ->sum('montant_total')
            + (float) Vente::where('statut', 'valide')
            ->whereDate('date_vente', '>=', $moisDebut)
            ->whereDate('date_vente', '<=', $moisFin)
            ->sum('montant');

        // Total dépenses de la période
        $totalDepenses = (float) Depense::whereDate('date_depense', '>=', $moisDebut)
            ->whereDate('date_depense', '<=', $moisFin)
            ->sum('montant');

        // Répartition par catégorie
        $parCategorie = Depense::whereDate('date_depense', '>=', $moisDebut)
            ->whereDate('date_depense', '<=', $moisFin)
            ->groupBy('categorie')
            ->select('categorie', DB::raw('SUM(montant) as total'), DB::raw('COUNT(*) as nb'))
            ->get();

        return response()->json([
            'depenses'      => $query->paginate(30),
            'resume'        => [
                'periode_debut'   => $moisDebut,
                'periode_fin'     => $moisFin,
                'ca'              => $ca,
                'total_depenses'  => $totalDepenses,
                'benefice_net'    => $ca - $totalDepenses,
                'par_categorie'   => $parCategorie,
            ],
            'categories'    => Depense::categories(),
        ]);
    }

    /**
     * POST /api/depenses
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categorie'    => 'required|string|in:' . implode(',', Depense::categories()),
            'libelle'      => 'required|string|max:255',
            'montant'      => 'required|numeric|min:1',
            'date_depense' => 'required|date',
            'notes'        => 'nullable|string',
        ]);

        $data['created_by'] = $request->user()->id;
        $depense = Depense::create($data);
        $depense->load('createdBy:id,nom,prenom');

        AuditService::log('create', 'Depense', $depense->id, null, $data);

        return response()->json($depense, 201);
    }

    /**
     * PUT /api/depenses/{depense}
     */
    public function update(Request $request, Depense $depense): JsonResponse
    {
        $data = $request->validate([
            'categorie'    => 'sometimes|string|in:' . implode(',', Depense::categories()),
            'libelle'      => 'sometimes|string|max:255',
            'montant'      => 'sometimes|numeric|min:1',
            'date_depense' => 'sometimes|date',
            'notes'        => 'nullable|string',
        ]);

        $old = $depense->toArray();
        $depense->update($data);

        AuditService::log('update', 'Depense', $depense->id, $old, $data);

        return response()->json($depense->fresh('createdBy:id,nom,prenom'));
    }

    /**
     * DELETE /api/depenses/{depense}
     */
    public function destroy(Depense $depense): JsonResponse
    {
        AuditService::log('delete', 'Depense', $depense->id);
        $depense->delete();
        return response()->json(['message' => 'Dépense supprimée.']);
    }
}
