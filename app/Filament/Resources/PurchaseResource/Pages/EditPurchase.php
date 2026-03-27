<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class EditPurchase extends EditRecord
{
    protected static string $resource = PurchaseResource::class;

    public function getTitle(): string
    {
        return 'Editar Compra';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Eliminar')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Eliminar Compra')
                ->modalDescription('¿Estás seguro de que deseas eliminar esta compra?')
                ->modalSubmitActionLabel('Sí, eliminar')
                ->modalCancelActionLabel('Cancelar'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Compra actualizada exitosamente';
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Compra actualizada')
            ->body('Los cambios han sido guardados correctamente.')
            ->icon('heroicon-o-check-circle');
    }

    // 🌟 NUEVO: Si editan la compra y agregan un vencimiento, aseguramos que exista en el inventario
    protected function afterSave(): void
    {
        $compra = $this->record;
        $features = Auth::user()->tenant->businessSector->features ?? [];
        $hasLots = $features['has_lots'] ?? false;
        $hasExpiry = $features['has_expiry_dates'] ?? false;

        if (!$hasLots && !$hasExpiry) return;

        foreach ($compra->items as $item) {
            if (!empty($item['expiration_date']) || !empty($item['batch_number'])) {
                $batchNumber = $item['batch_number'] ?? null;
                $expirationDate = $item['expiration_date'] ?? null;

                if (empty($batchNumber) && !empty($expirationDate)) {
                    $batchNumber = 'VENC-' . Carbon::parse($expirationDate)->format('Ymd');
                }

                if (!empty($batchNumber)) {
                    // Actualizamos o creamos el lote si no existe.
                    // Nota: Si cambian cantidades en la edición, la lógica de Kardex se vuelve compleja.
                    // Por ahora, nos aseguramos de que el lote al menos exista en la BD.
                    \Percy\Core\Models\ProductBatch::updateOrCreate(
                        [
                            'tenant_id' => $compra->tenant_id,
                            'product_id' => $item['product_id'],
                            'batch_number' => strtoupper($batchNumber),
                        ],
                        [
                            'expiration_date' => $expirationDate,
                        ]
                    );
                }
            }
        }
    }
}
