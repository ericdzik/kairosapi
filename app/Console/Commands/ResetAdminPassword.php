<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetAdminPassword extends Command
{
    protected $signature   = 'kairos:reset-admin
                                {--telephone=0700000000 : Numéro de téléphone du compte à resetter}
                                {--password= : Nouveau mot de passe (défaut : valeur de ADMIN_PASSWORD)}';

    protected $description = 'Resets the admin/directeur account password and reactivates it';

    public function handle(): int
    {
        $telephone = $this->option('telephone');
        $password  = $this->option('password') ?? env('ADMIN_PASSWORD', 'Kairos@2024!');

        $user = User::withTrashed()->where('telephone', $telephone)->first();

        if (!$user) {
            $this->error("❌ Aucun utilisateur trouvé avec le numéro : {$telephone}");
            return self::FAILURE;
        }

        $user->restore(); // au cas où il serait soft-deleted
        $user->update([
            'password'    => Hash::make($password),
            'actif'       => true,
            'first_login' => true,
        ]);

        // Révoquer tous les tokens existants pour forcer une nouvelle connexion
        $user->tokens()->delete();

        $this->info("✅ Compte réinitialisé avec succès.");
        $this->line("   Téléphone : {$telephone}");
        $this->line("   Mot de passe : {$password}");
        $this->line("   Rôle : {$user->role}");
        $this->warn("⚠️  Changez ce mot de passe immédiatement après connexion !");

        return self::SUCCESS;
    }
}
