<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // En lugar de User::create, usamos updateOrCreate
        // Busca por email; si existe, lo actualiza. Si no, lo crea.
        $superAdmin = \App\Models\User::updateOrCreate(
            ['email' => 'percyrojasrod@gmail.com'], // Condición de búsqueda
            [
                'name' => 'Súper Administrador',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'tenant_id' => null, // Descomenta esto si ya tienes un tenant creado
            ]
        );

        // Le asignamos el rol con ID 1 (Administrador) solo si no lo tiene
        $superAdmin->roles()->syncWithoutDetaching([1]);


        $this->call([
            \Percy\Core\Database\Seeders\BusinessSectorSeeder::class,
            \Percy\Core\Database\Seeders\SunatSeeder::class,
            \Percy\Core\Database\Seeders\RoleSeeder::class,
        ]);


    }
}
