<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;

    public function getTitle(): string
    {
        return 'Registrar Nueva Compra';
    }

    protected function getRedirectUrl(): string
    {
      return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Auth::user()->tenant_id;
        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Compra registrada exitosamente';
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Compra registrada')
            ->body('La compra ha sido registrada correctamente en el sistema.')
            ->icon('heroicon-o-check-circle');
    }

    // 🌟 NUEVO: Lógica que se ejecuta INMEDIATAMENTE DESPUÉS de guardar la compra en BD
    protected function afterCreate(): void
    {
        $compra = $this->record;
        $features = Auth::user()->tenant->businessSector->features ?? [];
        $hasLots = $features['has_lots'] ?? false;
        $hasExpiry = $features['has_expiry_dates'] ?? false;

        // Si el negocio no usa ni lotes ni vencimientos (Ferretería, Ropa), no hacemos nada
        if (!$hasLots && !$hasExpiry) return;

        // Recorremos todos los productos que acabamos de comprar
        foreach ($compra->items as $item) {

            // Verificamos si en el formulario llenaron la fecha de vencimiento
            if (!empty($item['expiration_date']) || !empty($item['batch_number'])) {

                $batchNumber = $item['batch_number'] ?? null;
                $expirationDate = $item['expiration_date'] ?? null;

                // MAGIA PARA MINIMARKET: Si no hay lote, pero sí vencimiento, creamos uno interno
                if (empty($batchNumber) && !empty($expirationDate)) {
                    $batchNumber = 'VENC-' . Carbon::parse($expirationDate)->format('Ymd');
                }

                if (!empty($batchNumber)) {
                    // Buscamos si ya existe este lote en el producto, si no, lo creamos
                    $batch = \Percy\Core\Models\ProductBatch::firstOrCreate(
                        [
                            'tenant_id' => $compra->tenant_id,
                            'product_id' => $item['product_id'],
                            'batch_number' => strtoupper($batchNumber),
                        ],
                        [
                            'expiration_date' => $expirationDate,
                            'initial_quantity' => 0,
                            'current_quantity' => 0,
                            'manufacturing_date' => null,
                            'is_active' => true,
                        ]
                    );

                    // Le sumamos la cantidad que acabamos de comprar
                    $batch->initial_quantity += $item['quantity'];
                    $batch->current_quantity += $item['quantity'];

                    // Actualizamos la fecha de vencimiento por si acaso
                    if ($expirationDate) {
                        $batch->expiration_date = $expirationDate;
                    }

                    $batch->save();

                    // 🌟 ACTUALIZAMOS EL STOCK GLOBAL DEL PRODUCTO
                    $product = \Percy\Core\Models\Product::find($item['product_id']);
                    if ($product) {
                        $stockRealExacto = $product->batches()->where('is_active', true)->sum('current_quantity');
                        $product->current_stock = $stockRealExacto;
                        $product->save();

                        // 🌟 REGISTRAMOS EL MOVIMIENTO EN EL KARDEX
                        \Percy\Core\Models\InventoryMovement::create([
                            'tenant_id'        => $compra->tenant_id,
                            'product_id'       => $product->id,
                            'product_batch_id' => $batch->id,
                            'user_id'          => Auth::id(),
                            'type'             => 'IN',
                            'quantity'         => $item['quantity'],
                            'balance_after'    => $product->current_stock,
                            'reason'           => 'Compra: ' . ($compra->document_number ?? 'S/N'),
                        ]);
                    }
                }
            }
        }
    }
}
