<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SaleController;

Route::redirect('/', '/admin');

// Reemplazamos la función anónima por una llamada al controlador
Route::get('/sales/{sale}/ticket', [SaleController::class, 'printTicket'])->name('sales.ticket');

Route::get('/sales/{sale}/download-xml', [SaleController::class, 'downloadXml'])->name('sales.download-xml');
Route::get('/sales/{sale}/download-cdr', [SaleController::class, 'downloadCdr'])->name('sales.download-cdr');
