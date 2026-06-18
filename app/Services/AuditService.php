<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Enregistre une action dans l'audit log.
     */
    public static function log(
        string $action,
        string $entite,
        ?string $entiteId = null,
        ?array $ancienneValeur = null,
        ?array $nouvelleValeur = null
    ): void {
        AuditLog::create([
            'user_id'         => Auth::id(),
            'action'          => $action,
            'entite'          => $entite,
            'entite_id'       => $entiteId,
            'ancienne_valeur' => $ancienneValeur,
            'nouvelle_valeur' => $nouvelleValeur,
            'ip'              => Request::ip(),
            'device_id'       => Request::header('X-Device-ID'),
        ]);
    }
}
