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
     * Notifier le bureau quand une mise est enregistrée (validée automatiquement)
     */
    public static function nouvelleMise(string $clientNom, string $commercialNom, float $montant): void
    {
        $montantFmt = number_format($montant, 0, ',', ' ');
        $message = "$commercialNom a enregistré une mise de {$montantFmt} FCFA pour $clientNom.";
        self::notifierRole('directeur',    'Nouvelle mise enregistrée', $message, 'mise');
        self::notifierRole('comptabilite', 'Nouvelle mise enregistrée', $message, 'mise');
    }

    /**
     * Notifier quand une tontine est terminée (100% cotisé)
     */
    public static function tontineTerminee(string $clientNom, string $commercialId): void
    {
        // Notifier le commercial
        self::envoyer($commercialId, 'Tontine complète !',
            "$clientNom a complété toutes ses mises. Prêt à livrer.", 'livraison');

        // Notifier le bureau
        $message = "$clientNom a complété sa tontine et est prêt à être livré.";
        self::notifierRole('directeur',    'Client prêt à livrer', $message, 'livraison');
        self::notifierRole('comptabilite', 'Client prêt à livrer', $message, 'livraison');
        self::notifierRole('secretaire',   'Client prêt à livrer', $message, 'livraison');
    }

    /**
     * Notifier quand une livraison est effectuée
     */
    public static function livraisonEffectuee(string $clientNom, string $commercialId, string $directeurNom): void
    {
        // Notifier le commercial
        self::envoyer($commercialId, 'Livraison effectuée',
            "La livraison pour $clientNom a été enregistrée par $directeurNom.", 'livraison');

        // Notifier la comptabilité
        $message = "La tontine de $clientNom a été livrée par $directeurNom.";
        self::notifierRole('comptabilite', 'Livraison enregistrée', $message, 'livraison');
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