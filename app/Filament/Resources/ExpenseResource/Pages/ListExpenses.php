<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Percy\Core\Models\CashRegister;
use Illuminate\Validation\ValidationException;

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
                ->modalSubmitActionLabel('Registrar')
                ->createAnother(false)
                ->disabled(function () {
                    return ! CashRegister::query()
                        ->where('tenant_id', Auth::user()?->tenant_id)
                        ->where('status', 'open')
                        ->exists();
                })
                ->tooltip(function () {
                    $hasOpenCash = CashRegister::query()
                        ->where('tenant_id', Auth::user()?->tenant_id)
                        ->where('status', 'open')
                        ->exists();

                    return $hasOpenCash
                        ? ''
                        : 'Debes abrir una caja antes de registrar gastos.';
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $openCashRegister = CashRegister::query()
                        ->where('tenant_id', Auth::user()->tenant_id)
                        ->where('status', 'open')
                        ->latest('opened_at')
                        ->first();

                    if (! $openCashRegister) {
                        throw ValidationException::withMessages([
                            'cash_register_id' => 'Debes abrir una caja antes de registrar gastos.',
                        ]);
                    }

                    $data['tenant_id'] = Auth::user()->tenant_id;
                    $data['user_id'] = Auth::id();
                    $data['cash_register_id'] = $openCashRegister->id;

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
