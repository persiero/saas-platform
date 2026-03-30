<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    // 🌟 Evitamos que el mozo vea el escritorio y sus gráficos
    public static function canAccess(): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = Auth::user();

        // Solo permite el acceso si NO es Vendedor
        return !$user->hasRole('Vendedor');
    }
}
