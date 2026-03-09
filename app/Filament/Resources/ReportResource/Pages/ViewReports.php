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
}
