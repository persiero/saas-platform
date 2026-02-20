<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        
        // En producción, prevenir lazy loading para detectar problemas de N+1
        if (app()->environment('production')) {
            \Illuminate\Database\Eloquent\Model::preventLazyLoading();
        }
    }
}
