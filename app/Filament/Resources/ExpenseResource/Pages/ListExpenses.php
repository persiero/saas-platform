<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo Gasto')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->slideOver() // Se abre lateralmente
                ->mutateFormDataUsing(function (array $data): array {
                    $data['tenant_id'] = Auth::user()->tenant_id;
                    return $data;
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'Control de Gastos';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\ExpenseResource\Widgets\ExpenseStats::class,
        ];
    }
}
