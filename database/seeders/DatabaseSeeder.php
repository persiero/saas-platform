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
        \App\Models\User::updateOrCreate(
            ['email' => 'admin@admin.com'], // Condición de búsqueda
            [
                'name' => 'Administrador',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'tenant_id' => 1, // Descomenta esto si ya tienes un tenant creado
            ]
        );


        $this->call([
            \Percy\Core\Database\Seeders\BusinessSectorSeeder::class,
            \Percy\Core\Database\Seeders\SunatSeeder::class,
        ]);


    }
}
