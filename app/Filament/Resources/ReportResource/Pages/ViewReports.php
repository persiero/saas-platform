<?php

namespace App\Filament\Resources\ReportResource\Pages;

use App\Filament\Resources\ReportResource;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Percy\Core\Services\ReportService;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Percy\Core\Services\Tenants\TenantPlanService;

class ViewReports extends Page
{
    protected static string $resource = ReportResource::class;
    protected static string $view = 'filament.resources.report-resource.pages.view-reports';
    protected static ?string $title = 'Reportes y Análisis';

    public $startDate;
    public $endDate;
    public $salesData = [];
    public $topProducts = [];
    public $profitability = [];
    public $cashStatus = [];
    public $paymentMethodsData = [];

    public bool $canViewProfitability = false;
    public bool $canViewAdvancedReports = false;

    public $salesByChannel = [];
    public $pendingWebOrdersSummary = [];

    public bool $canViewOnlineStoreReports = false;

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');

        $this->syncReportPermissions();
        $this->loadReports();
    }

    private function tenantPlanHas(string $feature): bool
    {
        /** @var \Percy\Core\Models\User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->tenant) {
            return false;
        }

        return app(TenantPlanService::class)->has($feature, $user->tenant);
    }

    private function syncReportPermissions(): void
    {
        $this->canViewProfitability = $this->tenantPlanHas('has_profitability_reports');
        $this->canViewAdvancedReports = $this->tenantPlanHas('has_advanced_reports');

        $this->canViewOnlineStoreReports = $this->tenantPlanHas('has_online_store')
            || $this->tenantPlanHas('has_web_orders');
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Filtros de Análisis')
                ->schema([
                    Forms\Components\DatePicker::make('startDate')
                        ->label('Desde')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->maxDate(fn() => $this->endDate ?? now())
                        ->default(now()->startOfMonth())
                        ->prefixIcon('heroicon-o-calendar-days'),

                    Forms\Components\DatePicker::make('endDate')
                        ->label('Hasta')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->minDate(fn() => $this->startDate)
                        ->maxDate(now())
                        ->default(now())
                        ->prefixIcon('heroicon-o-calendar-days'),
                ])
                ->columns(2) // Coloca los calendarios uno al lado del otro
                ->collapsible() // Permite ocultarlos para ver los gráficos a pantalla completa
                ->compact(), // Reduce los márgenes internos
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // 🌟 NUEVO: Botón de Exportar a Excel
            \Filament\Actions\Action::make('export')
                ->label('Descargar Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action('exportToExcel'), // Llamará a una función que crearemos ahora

            \Filament\Actions\Action::make('refresh')
                ->label('Generar Reportes')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action('loadReports'), // Llama directamente a tu función
        ];
    }

    public function loadReports(): void
    {
        try {
            $service = app(ReportService::class);
            $tenantId = Auth::user()->tenant_id;

            $this->syncReportPermissions();

            $this->salesData = $service->salesByPeriod($tenantId, $this->startDate, $this->endDate);
            $this->topProducts = $service->topProducts($tenantId, $this->startDate, $this->endDate);
            $this->cashStatus = $service->cashRegisterStatus($tenantId);
            $this->paymentMethodsData = $service->paymentMethods($tenantId, $this->startDate, $this->endDate);

            $this->salesByChannel = $this->canViewOnlineStoreReports
                ? $service->salesByChannel($tenantId, $this->startDate, $this->endDate)
                : [];

            $this->pendingWebOrdersSummary = $this->canViewOnlineStoreReports
                ? $service->pendingWebOrdersSummary($tenantId, $this->startDate, $this->endDate)
                : [];

            $this->profitability = $this->canViewProfitability
                ? $service->profitability($tenantId, $this->startDate, $this->endDate)
                : [];

            Notification::make()
                ->success()
                ->title('Reportes actualizados')
                ->body('Los datos han sido cargados correctamente.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error al cargar reportes')
                ->body('Ocurrió un error al generar los reportes. Por favor, intenta nuevamente.')
                ->send();
        }
    }

    public function exportToExcel()
    {
        $tenantId = Auth::user()->tenant_id;

        // 🌟 MEJORA NIVEL DIOS: Cargamos las relaciones anidadas 'items.product'
        // Esto trae todas las ventas, sus detalles y el nombre del producto en 1 sola consulta
        $service = app(ReportService::class);

        $sales = $service->salesForExport(
            $tenantId,
            $this->startDate,
            $this->endDate
        );

        $fileName = 'reporte_ventas_detallado_' . now()->format('Ymd_Hi') . '.csv';

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function () use ($sales) {
            $file = fopen('php://output', 'w');

            fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // 🌟 AGREGAMOS LAS COLUMNAS DEL PRODUCTO Y CANTIDAD
            fputcsv($file, [
                'Fecha',
                'Tipo Comprobante',
                'Serie y Numero',
                'Doc. Cliente',
                'Nombre Cliente',
                'Cajero',
                'Metodo de Pago',
                'Estado SUNAT',
                'Canal',
                'Producto',          // <-- NUEVO
                'Cantidad',          // <-- NUEVO
                'Precio Unit. (S/)', // <-- NUEVO
                'Total Fila (S/)',   // <-- NUEVO (Cantidad x Precio)
                'Total Comprobante (S/)'
            ], ';');

            foreach ($sales as $sale) {
                $tipoDoc = match ($sale->document_type) {
                    '01' => 'Factura',
                    '03' => 'Boleta',
                    default => 'Nota de Venta'
                };

                $docCliente = $sale->customer ? ($sale->customer->document_number ?? '00000000') : '00000000';
                $nomCliente = $sale->customer ? ($sale->customer->name ?? 'CLIENTES VARIOS') : 'CLIENTES VARIOS';
                $nomCajero  = $sale->user ? ($sale->user->name ?? 'Sistema') : 'Sistema';
                $serieNum   = $sale->series . '-' . str_pad($sale->correlative, 6, '0', STR_PAD_LEFT);
                $canal = $sale->channel === 'ecommerce' ? 'Tienda Online' : 'Presencial';

                // 🌟 LÓGICA DE DETALLE: Recorremos los ítems de esta venta
                if ($sale->items && $sale->items->count() > 0) {
                    foreach ($sale->items as $item) {
                        // 🌟 MAGIA EXCEL: Si tiene producto en catálogo, usa ese.
                        // Si no tiene (como el hospedaje), usa el nombre impreso en el ticket.
                        $nombreProducto = 'Desconocido';
                        if ($item->product) {
                            $nombreProducto = $item->product->name;
                        } elseif ($item->item_name) {
                            $nombreProducto = $item->item_name;
                        }

                        // Calculamos el total de la fila.
                        // Nota: Si tu modelo SaleItem usa otros nombres (ej. unit_price), ajústalos aquí
                        $precioUnitario = $item->unit_price ?? 0;
                        $totalFila = $precioUnitario * $item->quantity;

                        fputcsv($file, [
                            \Carbon\Carbon::parse($sale->sold_at)->format('d/m/Y H:i'),
                            $tipoDoc,
                            $serieNum,
                            $docCliente,
                            $nomCliente,
                            $nomCajero,
                            $sale->payment_method ?? 'No especificado',
                            strtoupper($sale->sunat_status ?? 'NO ENVIADO'),
                            $canal,
                            $nombreProducto,
                            $item->quantity,
                            number_format($precioUnitario, 2, '.', ''),
                            number_format($totalFila, 2, '.', ''),
                            number_format($sale->total, 2, '.', '')
                        ], ';');
                    }
                } else {
                    // Si por algún motivo la venta no tiene ítems guardados, imprimimos la fila genérica
                    fputcsv($file, [
                        \Carbon\Carbon::parse($sale->sold_at)->format('d/m/Y H:i'),
                        $tipoDoc,
                        $serieNum,
                        $docCliente,
                        $nomCliente,
                        $nomCajero,
                        $sale->payment_method ?? 'No especificado',
                        strtoupper($sale->sunat_status ?? 'NO ENVIADO'),
                        'SIN DETALLE DE PRODUCTOS',
                        '0',
                        '0.00',
                        '0.00',
                        number_format($sale->total, 2, '.', '')
                    ], ';');
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
