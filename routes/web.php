<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Auth;

// 🌟 1. PRIMERO LAS RUTAS DE SUBDOMINIOS (TIENDAS SAAS)
// Al ponerlas aquí arriba, Laravel las evalúa antes que cualquier redirección global.
Route::domain('{tenant_domain}.' . env('APP_URL_BASE', 'saas-platform.test'))->group(function () {
    // Ruta del Catálogo Principal (La que ya tenías)
    Route::get('/', [\App\Http\Controllers\StorefrontController::class, 'index'])->name('storefront.index');

    // 🌟 Ruta del Checkout (Apuntando al controlador nuevo)
    Route::get('/checkout', [\App\Http\Controllers\StorefrontController::class, 'checkout'])->name('storefront.checkout');

    // 🌟 LA NUEVA RUTA POST (Asegúrate de que esta línea exista aquí adentro)
    Route::post('/checkout/process', [\App\Http\Controllers\StorefrontController::class, 'processWebOrder'])->name('storefront.process');
});

// 🌟 2. RUTAS GLOBALES DEL SISTEMA PRINCIPAL
// Si alguien entra a "saas-platform.test" (sin subdominio), lo mandamos al panel admin.
Route::redirect('/', '/admin');

// 🌟 3. OTRAS RUTAS DEL SISTEMA (Tickets, PDF, Caché, etc.)
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/sales/{sale}/ticket', [SaleController::class, 'printTicket'])
        ->name('sales.ticket');

    Route::get('/sales/{sale}/download-xml', [SaleController::class, 'downloadXml'])
        ->name('sales.download-xml');

    Route::get('/sales/{sale}/download-cdr', [SaleController::class, 'downloadCdr'])
        ->name('sales.download-cdr');

    Route::get('/print/kitchen/{id}', function ($id) {
        $sale = \Percy\Core\Models\Sale::with(['items', 'table.zone', 'user'])->findOrFail($id);

        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        abort_unless($user && $user->tenant_id === $sale->tenant_id, 403);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.kitchen-ticket', compact('sale'))
            ->setPaper([0, 0, 226.77, 800], 'portrait');

        return $pdf->stream('comanda-mesa-' . $sale->id . '.pdf');
    })->name('print.kitchen');
});
