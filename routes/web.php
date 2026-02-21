<?php

use Illuminate\Support\Facades\Route;
use Barryvdh\DomPDF\Facade\Pdf;
use Percy\Core\Models\Sale;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sales/{sale}/ticket', function (Sale $sale) {
    // Cargamos la vista y le pasamos los datos de la venta
    $pdf = Pdf::loadView('pdf.ticket', compact('sale'));
    
    // Configuramos el tamaño del papel: Ancho 80mm (aprox 226 puntos), Alto automático
    $pdf->setPaper([0, 0, 226.77, 1000], 'portrait'); 
    
    // stream() lo muestra en el navegador en lugar de descargarlo de golpe
    return $pdf->stream('ticket-' . $sale->series . '-' . $sale->correlative . '.pdf');
})->name('sales.ticket');