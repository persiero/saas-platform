<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SaleController;

/*
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
*/

Route::any('{any}', function () {
    return '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Virtual Perú - Software POS</title>
        <style>
            body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f3f4f6; color: #1f2937; margin: 0; text-align: center; }
            .container { max-width: 600px; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-top: 5px solid #f59e0b; }
            h1 { color: #111827; margin-bottom: 10px;}
            p { font-size: 16px; line-height: 1.5; color: #4b5563; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Virtual Perú - Software de Gestión</h1>
            <p>Nuestra plataforma B2B para Minimarkets y Restaurantes se encuentra en fase de desarrollo y mantenimiento programado.</p>
            <p><strong>Estado:</strong> Solo acceso administrativo interno. No se admiten registros públicos ni transacciones comerciales en este momento.</p>
        </div>
    </body>
    </html>';
})->where('any', '.*');
