<?php

namespace App\Filament\Resources\CashRegisterResource\Pages;

use App\Filament\Resources\CashRegisterResource;
use Percy\Core\Models\CashRegister;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListCashRegisters extends ListRecords
{
    protected static string $resource = CashRegisterResource::class;

    protected static ?string $title = 'Control de Cajas';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Abrir Nueva Caja')
                ->icon('heroicon-o-lock-open')
                ->modalHeading('Apertura de Turno')
                ->modalWidth('sm') // Modal pequeño y elegante

                // 🌟 MAGIA VISUAL: Bloquea el botón si ya hay una caja abierta
                ->disabled(function () {
                    return CashRegister::where('tenant_id', Auth::user()->tenant_id)
                        ->where('user_id', Auth::id())
                        ->where('status', 'open')
                        ->exists();
                })
                // 🌟 TOOLTIP: Le explica al usuario por qué el botón está bloqueado
                ->tooltip(function () {
                    $hasOpenCash = CashRegister::where('tenant_id', Auth::user()->tenant_id)
                        ->where('user_id', Auth::id())
                        ->where('status', 'open')
                        ->exists();

                    return $hasOpenCash ? 'Debes cerrar tu caja actual para abrir una nueva.' : '';
                })

                // ESTE ES TU CANDADO BACKEND (Lo dejamos como doble seguridad):
                ->action(function (array $data) {
                    $hasOpenCash = CashRegister::where('tenant_id', Auth::user()->tenant_id)
                        ->where('user_id', Auth::id())
                        ->where('status', 'open')
                        ->exists();

                    if ($hasOpenCash) {
                        Notification::make()
                            ->title('Acción Denegada')
                            ->body('Ya tienes una caja abierta. Ciérrala primero.')
                            ->warning()
                            ->send();
                        return; // Detiene la creación
                    }

                    // Si pasa el candado, crea la caja
                    CashRegister::create([
                        'tenant_id' => Auth::user()->tenant_id,
                        'user_id' => Auth::id(),
                        'opening_amount' => $data['opening_amount'],
                        'status' => 'open',
                        'opened_at' => now(),
                    ]);

                    Notification::make()->title('Caja Abierta')->success()->send();
                }),
        ];
    }

}
