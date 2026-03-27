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

            // 🌟 MAGIA SAAS: Usamos el SLUG en lugar del ID, ¡es mucho más seguro!
                $sectorSlug = Auth::user()->tenant->businessSector->slug ?? 'general';

            // Registramos el color primario dinámicamente según el giro de negocio
            FilamentColor::register([
                'primary' => match ($sectorSlug) {
                    'pharmacy'   => Color::Teal,     // 🟢 Verde azulado (Salud, Farmacias)
                    'minimarket' => Color::Amber,    // 🟠 Ámbar/Naranja (Retail, Bodegas, Minimarkets)
                    'restaurant' => Color::Rose,     // 🔴 Rosa/Rojo (Apetito, Restaurantes, Cafeterías)
                    'general'    => Color::Blue,     // 🔵 Azul (Tecnología, Ropa, Comercio General)
                    default      => Color::Indigo,   // 🟣 Color por defecto de Filament
                },
            ]);
        }

        return $next($request);
    }
}
