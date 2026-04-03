<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SaleController;

// 🌟 1. PRIMERO LAS RUTAS DE SUBDOMINIOS (TIENDAS SAAS)
// Al ponerlas aquí arriba, Laravel las evalúa antes que cualquier redirección global.
Route::domain('{tenant_domain}.' . env('APP_URL_BASE', 'saas-platform.test'))->group(function () {
    Route::get('/', [\App\Http\Controllers\StorefrontController::class, 'index'])->name('storefront.index');
});

// 🌟 2. RUTAS GLOBALES DEL SISTEMA PRINCIPAL
// Si alguien entra a "saas-platform.test" (sin subdominio), lo mandamos al panel admin.
Route::redirect('/', '/admin');

// 🌟 3. OTRAS RUTAS DEL SISTEMA (Tickets, PDF, Caché, etc.)
Route::get('/sales/{sale}/ticket', [SaleController::class, 'printTicket'])->name('sales.ticket');
Route::get('/sales/{sale}/download-xml', [SaleController::class, 'downloadXml'])->name('sales.download-xml');
Route::get('/sales/{sale}/download-cdr', [SaleController::class, 'downloadCdr'])->name('sales.download-cdr');

// Ruta para el ticket de cocina (Preparación) - AHORA COMO PDF REAL
Route::get('/print/kitchen/{id}', function ($id) {
    $sale = \Percy\Core\Models\Sale::with(['items', 'table.zone', 'user'])->findOrFail($id);

    // Generamos el PDF. El array [0, 0, 226.77, 800] representa 80mm de ancho.
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.kitchen-ticket', compact('sale'))
        ->setPaper([0, 0, 226.77, 800], 'portrait');

    // Mostramos el PDF en el navegador igual que la Nota de Venta
    return $pdf->stream('comanda-mesa-'.$sale->id.'.pdf');

})->name('print.kitchen');

Route::get('/limpiar-cache', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('optimize:clear');
        return "✅ Caché del servidor eliminada con éxito. Laravel ya puede leer Cloudflare.";
    } catch (\Exception $e) {
        return "❌ Error limpiando caché: " . $e->getMessage();
    }
});
