<?php

namespace App\Filament\Resources\PurchaseResource\Actions;

use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Percy\Core\Models\Purchase;
use Percy\Core\Services\Purchases\PurchaseService;

class PurchaseTableActions
{
    public static function actions(): array
    {
        return [
            Tables\Actions\ViewAction::make()
                ->label('Ver detalles')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->modalHeading('Detalles de la Compra')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalWidth('6xl'),

            Tables\Actions\EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil')
                ->color('warning')
                ->visible(
                    fn(Purchase $record): bool =>
                    $record->status === 'pending' &&
                        (Auth::user()?->canEditPurchases() ?? false)
                ),

            Tables\Actions\Action::make('confirmPendingPurchase')
                ->label('Confirmar compra')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(
                    fn(Purchase $record): bool =>
                    $record->status === 'pending' &&
                        (Auth::user()?->canEditPurchases() ?? false)
                )
                ->requiresConfirmation()
                ->modalHeading('Confirmar compra pendiente')
                ->modalDescription('Al confirmar esta compra, los productos ingresarán al inventario y se registrará el movimiento en Kardex. Luego ya no podrá editarse libremente.')
                ->modalSubmitActionLabel('Sí, confirmar')
                ->modalCancelActionLabel('Cancelar')
                ->action(function (Purchase $record): void {
                    try {
                        app(PurchaseService::class)->confirmPendingPurchase($record);

                        Notification::make()
                            ->success()
                            ->title('Compra confirmada')
                            ->body('La compra fue confirmada y el stock ingresó correctamente al inventario.')
                            ->send();
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->danger()
                            ->title('No se pudo confirmar la compra')
                            ->body(collect($e->errors())->flatten()->first() ?? 'Verifica la compra antes de confirmar.')
                            ->persistent()
                            ->send();
                    }
                }),

            Tables\Actions\Action::make('cancelPendingPurchase')
                ->label('Cancelar compra')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(
                    fn(Purchase $record): bool =>
                    $record->status === 'pending' &&
                        (Auth::user()?->canEditPurchases() ?? false)
                )
                ->requiresConfirmation()
                ->modalHeading('Cancelar compra pendiente')
                ->modalDescription('Esta compra aún no afecta inventario. Se marcará como cancelada y quedará como historial.')
                ->modalSubmitActionLabel('Sí, cancelar compra')
                ->modalCancelActionLabel('Volver')
                ->action(function (Purchase $record): void {
                    try {
                        app(PurchaseService::class)->cancelPendingPurchase($record);

                        Notification::make()
                            ->success()
                            ->title('Compra cancelada')
                            ->body('La compra pendiente fue cancelada correctamente.')
                            ->send();
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->danger()
                            ->title('No se pudo cancelar la compra')
                            ->body(collect($e->errors())->flatten()->first() ?? 'Verifica la compra antes de cancelar.')
                            ->persistent()
                            ->send();
                    }
                }),

            Tables\Actions\Action::make('voidCompletedPurchase')
                ->label('Anular compra')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->visible(
                    fn(Purchase $record): bool =>
                    $record->status === 'completed' &&
                        (Auth::user()?->canVoidCompletedPurchases() ?? false)
                )
                ->requiresConfirmation()
                ->modalHeading('Anular compra completada')
                ->modalDescription('Esta acción descontará del inventario los productos ingresados por esta compra y registrará un movimiento de salida en Kardex. No podrás editar la compra después de anularla.')
                ->modalSubmitActionLabel('Sí, anular compra')
                ->modalCancelActionLabel('Volver')
                ->form([
                    Forms\Components\Textarea::make('void_reason')
                        ->label('Motivo de anulación')
                        ->placeholder('Ej: Documento registrado por error, compra duplicada, devolución al proveedor, etc.')
                        ->required()
                        ->maxLength(500)
                        ->rows(3),
                ])
                ->action(function (Purchase $record, array $data): void {
                    try {
                        app(PurchaseService::class)->voidCompletedPurchase(
                            $record,
                            $data['void_reason'] ?? null
                        );

                        Notification::make()
                            ->success()
                            ->title('Compra anulada')
                            ->body('La compra fue anulada y el stock fue descontado correctamente.')
                            ->send();
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->danger()
                            ->title('No se pudo anular la compra')
                            ->body(collect($e->errors())->flatten()->first() ?? 'Verifica el stock antes de anular.')
                            ->persistent()
                            ->send();
                    }
                }),

            Tables\Actions\DeleteAction::make()
                ->label('Eliminar borrador')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(
                    fn(Purchase $record): bool =>
                    $record->status === 'pending' &&
                        (Auth::user()?->canDeletePurchases() ?? false)
                )
                ->requiresConfirmation()
                ->modalHeading('Eliminar compra pendiente')
                ->modalDescription('Esta compra aún no afecta inventario. Puedes eliminarla si fue registrada por error.')
                ->modalSubmitActionLabel('Sí, eliminar')
                ->modalCancelActionLabel('Cancelar'),
        ];
    }
}
