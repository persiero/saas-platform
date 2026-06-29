<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StorefrontController;
use Barryvdh\DomPDF\Facade\Pdf;
use Percy\Core\Models\Sale;

/*
|--------------------------------------------------------------------------
| Tiendas por subdominio
|--------------------------------------------------------------------------
*/

Route::domain('{tenant_domain}.' . env('APP_URL_BASE', 'saas-platform.test'))
    ->where(['tenant_domain' => '[a-zA-Z0-9\-]+'])
    ->group(function () {
        Route::get('/', [StorefrontController::class, 'index'])
            ->name('storefront.index');

        Route::get('/checkout', [StorefrontController::class, 'checkout'])
            ->name('storefront.checkout');

        Route::post('/checkout/process', [StorefrontController::class, 'processWebOrder'])
            ->name('storefront.process');
    });

/*
|--------------------------------------------------------------------------
| Sistema principal
|--------------------------------------------------------------------------
*/

Route::redirect('/', '/admin');

/*
|--------------------------------------------------------------------------
| Rutas privadas
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    Route::prefix('sales/{sale}')
        ->whereNumber('sale')
        ->group(function () {
            Route::get('/ticket', [SaleController::class, 'printTicket'])
                ->name('sales.ticket');

            Route::get('/download-xml', [SaleController::class, 'downloadXml'])
                ->name('sales.download-xml');

            Route::get('/download-cdr', [SaleController::class, 'downloadCdr'])
                ->name('sales.download-cdr');
        });

    Route::get('/print/kitchen/{id}', function ($id) {
        $sale = Sale::with(['items', 'table.zone', 'user'])->findOrFail($id);

        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        abort_unless($user && $user->tenant_id === $sale->tenant_id, 403);

        $pdf = Pdf::loadView('pdf.kitchen-ticket', compact('sale'))
            ->setPaper([0, 0, 226.77, 800], 'portrait');

        return $pdf->stream('comanda-mesa-' . $sale->id . '.pdf');
    })
        ->whereNumber('id')
        ->name('print.kitchen');
});
