<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * GET /api/audit-logs
     * Rôle requis : directeur, controleur
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user:id,nom,prenom,role')
            ->orderByDesc('created_at');

        if ($request->filled('user_id'))   $query->where('user_id', $request->user_id);
        if ($request->filled('entite'))    $query->where('entite', $request->entite);
        if ($request->filled('action'))    $query->where('action', $request->action);
        if ($request->filled('date_debut')) $query->whereDate('created_at', '>=', $request->date_debut);
        if ($request->filled('date_fin'))   $query->whereDate('created_at', '<=', $request->date_fin);

        return response()->json($query->paginate(50));
    }
}
