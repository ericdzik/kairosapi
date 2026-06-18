<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * GET /api/users
     * Rôle requis : directeur
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('role'))  $query->where('role', $request->role);
        if ($request->filled('actif')) $query->where('actif', filter_var($request->actif, FILTER_VALIDATE_BOOLEAN));

        $users = $query->orderBy('nom')->paginate(20);

        return response()->json($users);
    }

    /**
     * POST /api/users
     * Directeur : tous les rôles
     * Secrétaire : uniquement commercial
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'nom'       => 'required|string|max:100',
            'prenom'    => 'required|string|max:100',
            'telephone' => 'required|string|max:20|unique:users,telephone',
            'password'  => 'required|string|min:8',
            'role'      => 'required|in:directeur,comptabilite,secretaire,controleur,commercial',
        ]);

        // La secrétaire ne peut créer que des commerciaux
        if ($user->role === 'secretaire' && $data['role'] !== 'commercial') {
            return response()->json([
                'message' => 'La secrétaire ne peut créer que des comptes commerciaux.',
            ], 403);
        }

        $id   = (string) \Illuminate\Support\Str::uuid();
        $user = User::create([
            ...$data,
            'id'          => $id,
            'password'    => Hash::make($data['password']),
            'first_login' => true,
            'actif'       => true,
        ]);

        // Recharger depuis la base pour avoir l'UUID généré par PostgreSQL
        $user = User::find($id) ?? $user;

        return response()->json([
            'id'          => $user->id ?? $id,
            'nom'         => $user->nom,
            'prenom'      => $user->prenom,
            'telephone'   => $user->telephone,
            'role'        => $user->role,
            'actif'       => true,
            'first_login' => true,
        ], 201);
    }

    /**
     * GET /api/users/{user}
     * Rôle requis : directeur
     */
    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }

    /**
     * PUT /api/users/{user}
     * Rôle requis : directeur
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'nom'       => 'sometimes|string|max:100',
            'prenom'    => 'sometimes|string|max:100',
            'telephone' => 'sometimes|string|max:20|unique:users,telephone,' . $user->id,
            'role'      => 'sometimes|in:directeur,comptabilite,secretaire,controleur,commercial',
        ]);

        $user->update($data);

        return response()->json($user);
    }

    /**
     * DELETE /api/users/{user}
     * Rôle requis : directeur (soft delete)
     */
    public function destroy(User $user): JsonResponse
    {
        // Empêcher l'auto-suppression
        if ($user->id === request()->user()->id) {
            return response()->json(['message' => 'Impossible de supprimer votre propre compte.'], 403);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }

    /**
     * PATCH /api/users/{user}/toggle
     * Activer ou désactiver un compte
     */
    public function toggle(User $user): JsonResponse
    {
        if ($user->id === request()->user()->id) {
            return response()->json(['message' => 'Impossible de désactiver votre propre compte.'], 403);
        }

        $user->update(['actif' => !$user->actif]);

        // Révoquer les tokens si désactivé
        if (!$user->actif) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => $user->actif ? 'Compte activé.' : 'Compte désactivé.',
            'actif'   => $user->actif,
        ]);
    }

    /**
     * PATCH /api/users/{user}/reset-password
     * Rôle requis : directeur, secretaire
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'new_password' => 'required|string|min:8',
        ]);

        $user->update([
            'password'    => Hash::make($data['new_password']),
            'first_login' => true,
        ]);

        $user->tokens()->delete();

        AuditService::log('reset_password', 'User', $user->id);

        return response()->json(['message' => 'Mot de passe réinitialisé. L\'utilisateur devra se reconnecter.']);
    }
}
