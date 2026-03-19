<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Validar que los modelos con tenant_id pertenezcan al tenant del usuario autenticado
        \Illuminate\Database\Eloquent\Model::preventAccessingMissingAttributes();

        // 🛡️ EN LOCAL: Prevenir lazy loading para que explote en tu cara y lo arregles
        if (! app()->environment('production')) {
            \Illuminate\Database\Eloquent\Model::preventLazyLoading();
        }

        // ☁️ EN PRODUCCIÓN: Forzar HTTPS para que Railway no rompa los estilos de Filament
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // 🌟 SISTEMA AUTOSANABLE: Crear directorios críticos si el volumen está vacío
        $directorios = [
            'app/private/certificates',
            'app/private/sunat',
            'app/livewire-tmp',
            'app/public'
        ];

        foreach ($directorios as $dir) {
            $path = storage_path($dir);
            if (!file_exists($path)) {
                // Crea la carpeta y todas las subcarpetas necesarias con permisos de escritura
                mkdir($path, 0775, true);
            }
        }
    }
}
