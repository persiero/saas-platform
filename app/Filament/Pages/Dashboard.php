<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    // 🌟 1. QUITAMOS EL CANDADO DEL 403: Dejamos que "entre" por un segundo
    public static function canAccess(): bool
    {
        return true;
    }

    // 🌟 2. EL SEMÁFORO (Teletransportador): Apenas pisa la página, lo redirigimos
    public function mount()
    {
        /** @var \Percy\Core\Models\User $user */
        $user = Auth::user();

        // Si es vendedor, lo mandamos al mapa de mesas al instante
        if ($user && $user->hasRole('Vendedor')) {
            // 🌟 CORRECCIÓN: Usamos return y quitamos el ->send()
            return redirect()->to(\App\Filament\Pages\PosRestaurant::getUrl());
        }

        // Si es Admin o Cajero, la función no hace nada y Filament carga los gráficos normales
    }
}
