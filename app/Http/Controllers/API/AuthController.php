<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    /**
     * POST /api/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telephone'    => 'required|string',
            'password'     => 'required|string',
            'device_token' => 'nullable|string',
        ]);

        $result = $this->authService->login(
            $data['telephone'],
            $data['password'],
            $data['device_token'] ?? null
        );

        return response()->json($result);
    }

    /**
     * POST /api/auth/refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $result = $this->authService->refresh($data['refresh_token']);

        return response()->json($result);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => 'Déconnecté avec succès.']);
    }

    /**
     * POST /api/auth/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password'              => 'required|string',
            'new_password'                  => 'required|string|min:8|confirmed',
            'new_password_confirmation'     => 'required|string',
        ]);

        $this->authService->changePassword(
            $request->user(),
            $data['current_password'],
            $data['new_password']
        );

        return response()->json(['message' => 'Mot de passe modifié. Veuillez vous reconnecter.']);
    }
}
