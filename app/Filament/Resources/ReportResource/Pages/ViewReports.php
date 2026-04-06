<?php

namespace App\Filament\Resources\ReportResource\Pages;

use App\Filament\Resources\ReportResource;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Percy\Core\Services\ReportService;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

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

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
        $this->loadReports();
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
                        ->maxDate(fn () => $this->endDate ?? now())
                        ->default(now()->startOfMonth())
                        ->prefixIcon('heroicon-o-calendar-days'),

                    Forms\Components\DatePicker::make('endDate')
                        ->label('Hasta')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->minDate(fn () => $this->startDate)
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
            $service = new ReportService();
            $tenantId = Auth::user()->tenant_id;

            $this->salesData = $service->salesByPeriod($tenantId, $this->startDate, $this->endDate);
            $this->topProducts = $service->topProducts($tenantId, $this->startDate, $this->endDate);
            $this->profitability = $service->profitability($tenantId, $this->startDate, $this->endDate);
            $this->cashStatus = $service->cashRegisterStatus($tenantId);

            // 🌟 NUEVO: Agrupamos las ventas por método de pago
            $this->paymentMethodsData = \Percy\Core\Models\Sale::where('tenant_id', $tenantId)
                ->whereBetween('sold_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
                ->where('status', '!=', 'canceled')
                ->select('payment_method', \Illuminate\Support\Facades\DB::raw('SUM(total) as total_amount'), \Illuminate\Support\Facades\DB::raw('COUNT(*) as transaction_count'))
                ->groupBy('payment_method')
                ->orderByDesc('total_amount')
                ->get()
                ->toArray();

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
        // 1. Obtenemos las ventas del período (usando las variables de tus filtros)
        $tenantId = Auth::user()->tenant_id;

        $sales = \Percy\Core\Models\Sale::where('tenant_id', $tenantId)
            ->whereBetween('sold_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
            ->where('status', '!=', 'canceled')
            ->orderBy('sold_at', 'desc')
            ->get();

        // 2. Nombre dinámico del archivo
        $fileName = 'reporte_ventas_' . now()->format('Ymd_Hi') . '.csv';

        // 3. Cabeceras HTTP para forzar la descarga en el navegador
        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        // 4. Construimos el archivo CSV "al vuelo"
        $callback = function() use($sales) {
            $file = fopen('php://output', 'w');

            // Agregar BOM para UTF-8 (Para que Excel lea tildes correctamente)
            fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Cabeceras del CSV (Usamos ';' por los estándares de Excel en Latam)
            fputcsv($file, [
                'Fecha',
                'Tipo Comprobante',
                'Serie y Numero',
                'Metodo de Pago', // <-- Nueva columna
                'Estado SUNAT',
                'Total (S/)'
            ], ';');

            // 5. Llenamos las filas con los nombres de columnas CORRECTOS
            foreach ($sales as $sale) {
                // Traductor rápido de tipo de documento
                $tipoDoc = match($sale->document_type) {
                    '01' => 'Factura',
                    '03' => 'Boleta',
                    default => 'Nota de Venta / Ticket'
                };

                fputcsv($file, [
                    \Carbon\Carbon::parse($sale->sold_at)->format('d/m/Y H:i'),
                    $tipoDoc,
                    // 🌟 CORRECCIÓN AQUÍ: Usamos series y correlative
                    $sale->series . '-' . str_pad($sale->correlative, 6, '0', STR_PAD_LEFT),
                    // 🌟 4. Agregamos el valor del metodo de pago
                    $sale->payment_method ?? 'No especificado',
                    strtoupper($sale->sunat_status ?? 'NO ENVIADO'),
                    number_format($sale->total, 2, '.', '')
                ], ';');
            }

            fclose($file);
        };

        // 6. Retornamos la descarga directa
        return response()->stream($callback, 200, $headers);
    }
}
