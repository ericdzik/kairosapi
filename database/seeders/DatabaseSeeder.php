<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ──────────────────────────────────────────────────────────────────
        // Compte Directeur par défaut
        // Mot de passe initial : défini par la variable d'env ADMIN_PASSWORD
        // (valeur par défaut en dev uniquement — à changer immédiatement
        //  via l'endpoint /api/auth/change-password après le premier login)
        // ──────────────────────────────────────────────────────────────────
        $adminPassword = env('ADMIN_PASSWORD', 'Kairos@2024!');

        $directeur = User::firstOrCreate(
            ['telephone' => '0700000000'],
            [
                'nom'         => 'Admin',
                'prenom'      => 'Kairos',
                'password'    => Hash::make($adminPassword),
                'role'        => 'directeur',
                'actif'       => true,
                'first_login' => true,
            ]
        );

        if ($directeur->wasRecentlyCreated) {
            $this->command->info('✅ Directeur créé : 0700000000 / ' . $adminPassword);
            $this->command->warn('⚠️  Changez ce mot de passe immédiatement via /api/auth/change-password !');
        } else {
            $this->command->info('ℹ️  Directeur déjà existant — aucune modification.');
        }
    }
}
