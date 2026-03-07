<?php

namespace App\Filament\Resources\ReportResource\Pages;

use App\Filament\Resources\ReportResource;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Percy\Core\Services\ReportService;
use Illuminate\Support\Facades\Auth;

class ViewReports extends Page
{
    protected static string $resource = ReportResource::class;
    protected static string $view = 'filament.resources.report-resource.pages.view-reports';

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
            Forms\Components\DatePicker::make('startDate')
                ->label('Fecha Inicio')
                ->required()
                ->default(now()->startOfMonth()),

            Forms\Components\DatePicker::make('endDate')
                ->label('Fecha Fin')
                ->required()
                ->default(now()),
        ];
    }

    public function loadReports(): void
    {
        $service = new ReportService();
        $tenantId = Auth::user()->tenant_id;

        $this->salesData = $service->salesByPeriod($tenantId, $this->startDate, $this->endDate);
        $this->topProducts = $service->topProducts($tenantId, $this->startDate, $this->endDate);
        $this->profitability = $service->profitability($tenantId, $this->startDate, $this->endDate);
        $this->cashStatus = $service->cashRegisterStatus($tenantId);
    }
}
