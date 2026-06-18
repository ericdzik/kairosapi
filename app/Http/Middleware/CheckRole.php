<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de vérification des rôles (RBAC)
 * Usage dans les routes : middleware('role:directeur,secretaire')
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles)) {
            return response()->json([
                'message' => 'Accès refusé. Rôle insuffisant.',
            ], 403);
        }

        return $next($request);
    }
}
