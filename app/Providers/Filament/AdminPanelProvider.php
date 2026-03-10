<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // 🚀 CONFIGURACIÓN DE BRANDING CON LOGOS PARA MODO CLARO Y OSCURO
            ->brandLogo(asset('images/logo.png')) // Logo para modo claro
            ->darkModeBrandLogo(asset('images/logo-dark.png')) // Logo para modo oscuro
            ->brandLogoHeight('4rem')

            // brandName aparece si el logo no carga, o en el login por defecto
            ->brandName('Virtual TI SaaS')

            // El Favicon (icono pequeño de la pestaña del navegador)
            ->favicon(asset('favicon.png')) // Asegúrate de tener un favicon.ico en public

            // 🌈 COLOR FIJO (El color del "SaaS" antes de entrar)
            ->colors([
                'primary' => Color::Indigo, // Usamos un azul profesional por defecto
            ])
            ->font('Inter')
            ->sidebarWidth('18rem')
            ->sidebarCollapsibleOnDesktop()
            // AQUÍ REGISTRAMOS EL MIDDLEWARE
            ->authMiddleware([
                \App\Http\Middleware\SetPanelTheme::class,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
