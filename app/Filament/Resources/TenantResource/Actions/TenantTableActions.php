<?php

namespace App\Filament\Resources\TenantResource\Actions;

use Filament\Notifications\Notification;
use Filament\Tables;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Models\Tenant;

class TenantTableActions
{
    public static function actions(): array
    {
        return [
            Tables\Actions\EditAction::make()
                ->label('Configurar')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('info')
                ->visible(fn (): bool => Auth::user()?->isSuperAdmin() ?? false),

            Tables\Actions\Action::make('suspendTenant')
                ->label('Suspender acceso')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->visible(fn (Tenant $record): bool =>
                    $record->is_active &&
                    (Auth::user()?->isSuperAdmin() ?? false)
                )
                ->requiresConfirmation()
                ->modalHeading('Suspender acceso del negocio')
                ->modalDescription(fn (Tenant $record): string =>
                    "El negocio {$record->name} no podrá acceder al panel hasta que sea reactivado. Esta opción puede usarse por falta de pago, revisión administrativa o suspensión temporal."
                )
                ->modalSubmitActionLabel('Sí, suspender')
                ->modalCancelActionLabel('Cancelar')
                ->action(function (Tenant $record): void {
                    $record->update([
                        'is_active' => false,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Negocio suspendido')
                        ->body('El acceso del negocio fue suspendido correctamente.')
                        ->send();
                }),

            Tables\Actions\Action::make('reactivateTenant')
                ->label('Reactivar acceso')
                ->icon('heroicon-o-lock-open')
                ->color('success')
                ->visible(fn (Tenant $record): bool =>
                    ! $record->is_active &&
                    (Auth::user()?->isSuperAdmin() ?? false)
                )
                ->requiresConfirmation()
                ->modalHeading('Reactivar acceso del negocio')
                ->modalDescription(fn (Tenant $record): string =>
                    "El negocio {$record->name} volverá a tener acceso al panel. Sus usuarios activos podrán iniciar sesión nuevamente."
                )
                ->modalSubmitActionLabel('Sí, reactivar')
                ->modalCancelActionLabel('Cancelar')
                ->action(function (Tenant $record): void {
                    $record->update([
                        'is_active' => true,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Negocio reactivado')
                        ->body('El acceso del negocio fue reactivado correctamente.')
                        ->send();
                }),
        ];
    }
}
