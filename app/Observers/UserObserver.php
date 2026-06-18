<?php

namespace App\Observers;

use App\Models\User;
use App\Services\AuditService;

class UserObserver
{
    public function created(User $user): void
    {
        AuditService::log('created', 'User', $user->id, null, $user->only(['nom', 'prenom', 'telephone', 'role']));
    }

    public function updated(User $user): void
    {
        $changes = $user->getChanges();
        unset($changes['updated_at'], $changes['password'], $changes['last_login_at']);

        if (empty($changes)) return;

        AuditService::log('updated', 'User', $user->id,
            array_intersect_key($user->getOriginal(), $changes),
            $changes
        );
    }

    public function deleted(User $user): void
    {
        AuditService::log('deleted', 'User', $user->id, $user->only(['nom', 'prenom', 'telephone', 'role']));
    }
}
