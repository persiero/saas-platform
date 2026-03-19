<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SaleController;

Route::redirect('/', '/admin');

// Reemplazamos la función anónima por una llamada al controlador
Route::get('/sales/{sale}/ticket', [SaleController::class, 'printTicket'])->name('sales.ticket');

Route::get('/sales/{sale}/download-xml', [SaleController::class, 'downloadXml'])->name('sales.download-xml');
Route::get('/sales/{sale}/download-cdr', [SaleController::class, 'downloadCdr'])->name('sales.download-cdr');

Route::get('/limpiar-cache', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('optimize:clear');
        return "✅ Caché del servidor eliminada con éxito. Laravel ya puede leer Cloudflare.";
    } catch (\Exception $e) {
        return "❌ Error limpiando caché: " . $e->getMessage();
    }
});
