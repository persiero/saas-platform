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
        // Primero ejecutar los seeders del Core
        $this->call([
            \Percy\Core\Database\Seeders\BusinessSectorSeeder::class,
            \Percy\Core\Database\Seeders\SunatSeeder::class,
            \Percy\Core\Database\Seeders\RoleSeeder::class,
            \Percy\Core\Database\Seeders\UbigeoSeeder::class,
        ]);

        // Luego crear o actualizar el Super Administrador
        $superAdmin = User::updateOrCreate(
            [
                'email' => 'percyrojasrod@gmail.com',
            ],
            [
                'name' => 'Súper Administrador',
                'password' => Hash::make('password123'),
                'tenant_id' => null,
            ]
        );

        // Asignar rol Administrador (ID = 1)
        $superAdmin->roles()->syncWithoutDetaching([1]);
    }
}
