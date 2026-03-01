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
        // Crear el usuario administrador por defecto
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@admin.com',
            'password' => Hash::make('password123'), // Siempre encripta la contraseña
            // 'tenant_id' => 1, // Descomenta esto si tu sistema exige que el usuario pertenezca a una empresa desde el inicio
        ]);

        // Si tienes otros seeders (como los de roles, permisos o tenants), llámalos aquí:
        // $this->call([
        //     TenantSeeder::class,
        // ]);
    }
}
