<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Compte Directeur par défaut
        User::create([
            'nom'         => 'Admin',
            'prenom'      => 'Kairos',
            'telephone'   => '0700000000',
            'password'    => Hash::make('kairos2024'),
            'role'        => 'directeur',
            'actif'       => true,
            'first_login' => true,
        ]);

        $this->command->info('Directeur créé : 0700000000 / kairos2024');
    }
}
