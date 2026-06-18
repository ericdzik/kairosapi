<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Envoyer une notification à un utilisateur
     */
    public static function envoyer(string $userId, string $titre, string $message, string $type = 'info'): void
    {
        Notification::create([
            'user_id' => $userId,
            'titre'   => $titre,
            'message' => $message,
            'type'    => $type,
        ]);
    }

    /**
     * Notifier tous les utilisateurs d'un rôle
     */
    public static function notifierRole(string $role, string $titre, string $message, string $type = 'info'): void
    {
        $users = User::where('role', $role)->where('actif', true)->get();
        foreach ($users as $user) {
            self::envoyer($user->id, $titre, $message, $type);
        }
    }

    /**
     * Notifier le bureau (directeur + comptabilité) d'une nouvelle mise en attente
     */
    public static function nouvelleMise(string $clientNom, string $commercialNom, float $montant): void
    {
        $message = "$commercialNom a enregistré une mise de {$montant} FCFA pour $clientNom.";
        self::notifierRole('directeur',    'Nouvelle mise en attente', $message, 'mise');
        self::notifierRole('comptabilite', 'Nouvelle mise en attente', $message, 'mise');
    }

    /**
     * Notifier quand une tontine est terminée (100%)
     */
    public static function tontineTerminee(string $clientNom, string $commercialId): void
    {
        self::envoyer($commercialId, 'Tontine complète !',
            "$clientNom a complété toutes ses mises. Prêt à livrer.", 'livraison');
        self::notifierRole('directeur', 'Client prêt à livrer',
            "$clientNom a complété sa tontine.", 'livraison');
    }

    /**
     * Notifier quand une mise est validée
     */
    public static function miseValidee(string $commercialId, string $clientNom, float $montant): void
    {
        self::envoyer($commercialId, 'Mise validée',
            "La mise de {$montant} FCFA pour $clientNom a été validée.", 'validation');
    }

    /**
     * Notifier quand une mise est rejetée
     */
    public static function miseRejetee(string $commercialId, string $clientNom, string $motif): void
    {
        self::envoyer($commercialId, 'Mise rejetée',
            "La mise pour $clientNom a été rejetée. Motif : $motif", 'rejet');
    }
}
