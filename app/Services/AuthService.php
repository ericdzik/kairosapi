<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private int $maxAttempts;
    private int $lockoutMinutes;

    public function __construct()
    {
        $this->maxAttempts    = (int) config('auth.login_max_attempts', 5);
        $this->lockoutMinutes = (int) config('auth.login_lockout_minutes', 15);
    }

    /**
     * Tente une connexion et retourne les tokens si succès.
     */
    public function login(string $telephone, string $password, ?string $deviceToken = null): array
    {
        $lockKey     = "login_lock:{$telephone}";
        $attemptsKey = "login_attempts:{$telephone}";

        Log::info('[AUTH] Tentative de connexion', ['telephone' => $telephone]);

        // Vérifier si le compte est bloqué
        if (Cache::has($lockKey)) {
            Log::warning('[AUTH] Compte bloqué (brute-force)', ['telephone' => $telephone]);
            throw ValidationException::withMessages([
                'telephone' => ["Compte temporairement bloqué. Réessayez dans {$this->lockoutMinutes} minutes."],
            ])->status(423);
        }

        $user = User::where('telephone', $telephone)->first();

        if (!$user) {
            Log::warning('[AUTH] Utilisateur introuvable', ['telephone' => $telephone]);
            Cache::increment($attemptsKey);
            Cache::put($attemptsKey, Cache::get($attemptsKey, 1), now()->addMinutes($this->lockoutMinutes));
            throw ValidationException::withMessages([
                'telephone' => ['Identifiants incorrects.'],
            ]);
        }

        Log::info('[AUTH] Utilisateur trouvé', [
            'telephone' => $telephone,
            'actif'     => $user->actif,
            'role'      => $user->role,
        ]);

        if (!Hash::check($password, $user->password)) {
            $attempts = Cache::increment($attemptsKey);
            Cache::put($attemptsKey, $attempts, now()->addMinutes($this->lockoutMinutes));

            Log::warning('[AUTH] Mot de passe incorrect', [
                'telephone' => $telephone,
                'attempts'  => $attempts,
                'max'       => $this->maxAttempts,
            ]);

            if ($attempts >= $this->maxAttempts) {
                Cache::put($lockKey, true, now()->addMinutes($this->lockoutMinutes));
                Cache::forget($attemptsKey);
                Log::warning('[AUTH] Compte bloqué après trop de tentatives', ['telephone' => $telephone]);
                throw ValidationException::withMessages([
                    'telephone' => ["Trop de tentatives. Compte bloqué {$this->lockoutMinutes} minutes."],
                ])->status(423);
            }

            throw ValidationException::withMessages([
                'telephone' => ['Identifiants incorrects.'],
            ]);
        }

        if (!$user->actif) {
            Log::warning('[AUTH] Compte désactivé', ['telephone' => $telephone]);
            throw ValidationException::withMessages([
                'telephone' => ['Ce compte est désactivé.'],
            ])->status(403);
        }

        // Réinitialiser les tentatives
        Cache::forget($attemptsKey);
        Cache::forget($lockKey);

        Log::info('[AUTH] Connexion réussie', ['telephone' => $telephone, 'role' => $user->role]);

        // Mettre à jour device token et last_login
        $user->update([
            'device_token'  => $deviceToken,
            'last_login_at' => now(),
        ]);

        // Générer les tokens
        $accessToken  = $user->createToken('access_token', ['*'], now()->addMinutes((int) config('auth.access_token_expiry', 15)));
        $refreshToken = $user->createToken('refresh_token', ['refresh'], now()->addMinutes((int) config('auth.refresh_token_expiry', 10080)));

        return [
            'access_token'  => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken->plainTextToken,
            'expires_in'    => config('auth.access_token_expiry', 15) * 60,
            'user'          => $user,
            'first_login'   => $user->first_login,
        ];
    }

    /**
     * Renouvelle l'access token via le refresh token.
     */
    public function refresh(string $refreshToken): array
    {
        // Trouver le token en base
        $tokenHash = hash('sha256', explode('|', $refreshToken)[1] ?? $refreshToken);
        $token = \Laravel\Sanctum\PersonalAccessToken::where('token', $tokenHash)
            ->where('name', 'refresh_token')
            ->first();

        if (!$token || ($token->expires_at && $token->expires_at->isPast())) {
            throw ValidationException::withMessages([
                'refresh_token' => ['Token invalide ou expiré.'],
            ])->status(401);
        }

        $user = $token->tokenable;

        if (!$user->actif) {
            throw ValidationException::withMessages([
                'telephone' => ['Ce compte est désactivé.'],
            ])->status(403);
        }

        // Révoquer l'ancien access token
        $user->tokens()->where('name', 'access_token')->delete();

        $accessToken = $user->createToken('access_token', ['*'], now()->addMinutes((int) config('auth.access_token_expiry', 15)));

        return [
            'access_token' => $accessToken->plainTextToken,
            'expires_in'   => config('auth.access_token_expiry', 15) * 60,
        ];
    }

    /**
     * Déconnexion : révoque tous les tokens de l'utilisateur.
     */
    public function logout(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Changement de mot de passe.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mot de passe actuel incorrect.'],
            ]);
        }

        $user->update([
            'password'    => Hash::make($newPassword),
            'first_login' => false,
        ]);

        // Révoquer tous les tokens pour forcer une reconnexion
        $user->tokens()->delete();
    }
}
