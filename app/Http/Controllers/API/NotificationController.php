<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(30);

        $nonLues = Notification::where('user_id', $request->user()->id)
            ->whereNull('lu_at')
            ->count();

        return response()->json([
            'non_lues'      => $nonLues,
            'notifications' => $notifications,
        ]);
    }

    /**
     * PATCH /api/notifications/{notification}/lire
     */
    public function lire(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $notification->update(['lu_at' => now()]);

        return response()->json(['message' => 'Notification marquée comme lue.']);
    }

    /**
     * PATCH /api/notifications/lire-tout
     */
    public function lireTout(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('lu_at')
            ->update(['lu_at' => now()]);

        return response()->json(['message' => 'Toutes les notifications marquées comme lues.']);
    }
}
