<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SaleController;

Route::redirect('/', '/admin');

// Reemplazamos la función anónima por una llamada al controlador
Route::get('/sales/{sale}/ticket', [SaleController::class, 'printTicket'])->name('sales.ticket');

Route::get('/sales/{sale}/download-xml', [SaleController::class, 'downloadXml'])->name('sales.download-xml');
Route::get('/sales/{sale}/download-cdr', [SaleController::class, 'downloadCdr'])->name('sales.download-cdr');

Route::get('/reparar-discos', function () {
    try {
        $rutas = [
            storage_path('app/livewire-tmp'),
            storage_path('app/private/certificates'),
            storage_path('app/private/sunat')
        ];

        $log = [];
        foreach ($rutas as $ruta) {
            // Si la carpeta no existe, la crea con permisos máximos
            if (!is_dir($ruta)) {
                mkdir($ruta, 0777, true);
                $log[] = "✅ Creado: " . $ruta;
            } else {
                $log[] = "⚡ Ya existía: " . $ruta;
            }
            // Fuerza los permisos de lectura y escritura para el servidor web
            @chmod($ruta, 0777);
        }

        return $log;
    } catch (\Exception $e) {
        return "❌ Error: " . $e->getMessage();
    }
});
