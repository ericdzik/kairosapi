<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocalisationController extends Controller
{
    /**
     * POST /api/localisation
     * Le commercial met à jour sa position GPS
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $request->user()->update([
            'latitude'     => $data['latitude'],
            'longitude'    => $data['longitude'],
            'last_seen_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * GET /api/commerciaux/carte
     * Directeur : positions en temps réel de tous les commerciaux actifs
     */
    public function carte(Request $request): JsonResponse
    {
        if (!$request->user()->isDirecteur()) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $commerciaux = User::where('role', 'commercial')
            ->where('actif', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id', 'nom', 'prenom', 'telephone', 'latitude', 'longitude', 'last_seen_at')
            ->get()
            ->map(function (User $u) {
                // Considérer "en ligne" si vu il y a moins de 30 minutes
                $enLigne = $u->last_seen_at && $u->last_seen_at->diffInMinutes(now()) <= 30;
                return [
                    'id'           => $u->id,
                    'nom'          => $u->nom . ' ' . $u->prenom,
                    'telephone'    => $u->telephone,
                    'latitude'     => (float) $u->latitude,
                    'longitude'    => (float) $u->longitude,
                    'last_seen_at' => $u->last_seen_at?->toDateTimeString(),
                    'en_ligne'     => $enLigne,
                    'derniere_vue' => $u->last_seen_at
                        ? $u->last_seen_at->diffForHumans()
                        : 'jamais',
                ];
            });

        return response()->json($commerciaux);
    }
}
