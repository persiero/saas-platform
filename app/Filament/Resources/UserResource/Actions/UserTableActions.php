<?php

namespace App\Filament\Resources\UserResource\Actions;

use Filament\Notifications\Notification;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UserTableActions
{
    public static function actions(): array
    {
        return [
            Tables\Actions\ViewAction::make()
                ->label('Ver detalles')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->modalHeading('Detalle del Usuario')
                ->modalCancelActionLabel('Cerrar'),

            Tables\Actions\EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil')
                ->color('warning'),

            Tables\Actions\Action::make('deactivateUser')
                ->label('Desactivar usuario')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->visible(
                    fn(Model $record): bool =>
                    (bool) $record->is_active &&
                        (Auth::user()?->isAdmin() ?? false) &&
                        Auth::id() !== $record->id
                )
                ->requiresConfirmation()
                ->modalHeading('Desactivar usuario')
                ->modalDescription(
                    fn(Model $record): string =>
                    "El usuario {$record->name} ya no podrá ingresar al sistema, pero su historial se conservará."
                )
                ->modalSubmitActionLabel('Sí, desactivar')
                ->modalCancelActionLabel('Cancelar')
                ->action(function (Model $record): void {
                    $record->update([
                        'is_active' => false,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Usuario desactivado')
                        ->body('El acceso del usuario fue desactivado correctamente.')
                        ->send();
                }),

            Tables\Actions\Action::make('reactivateUser')
                ->label('Reactivar usuario')
                ->icon('heroicon-o-lock-open')
                ->color('success')
                ->visible(
                    fn(Model $record): bool =>
                    ! (bool) $record->is_active &&
                        (Auth::user()?->isAdmin() ?? false)
                )
                ->requiresConfirmation()
                ->modalHeading('Reactivar usuario')
                ->modalDescription(
                    fn(Model $record): string =>
                    "El usuario {$record->name} podrá ingresar nuevamente al sistema."
                )
                ->modalSubmitActionLabel('Sí, reactivar')
                ->modalCancelActionLabel('Cancelar')
                ->action(function (Model $record): void {
                    $record->update([
                        'is_active' => true,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Usuario reactivado')
                        ->body('El acceso del usuario fue reactivado correctamente.')
                        ->send();
                }),
        ];
    }
}
