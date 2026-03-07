<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetPanelTheme
{
    public function handle(Request $request, Closure $next): Response
    {
        // Solo actuamos si el usuario está autenticado y tiene un tenant
        if (Auth::check() && Auth::user()->tenant) {

            $sectorId = Auth::user()->tenant->business_sector_id;

            // Registramos el color primario dinámicamente
            FilamentColor::register([
                'primary' => match ($sectorId) {
                    1 => Color::Blue,    // General
                    2 => Color::Amber,   // Restaurante (Caldos y Combinados Mary's)
                    3 => Color::Teal,    // Farmacia (Farmacia Ana)
                    default => Color::Indigo,
                },
            ]);
        }

        return $next($request);
    }
}
