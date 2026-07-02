<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Productos';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo Producto')
                ->icon('heroicon-o-plus-circle')
                ->visible(fn(): bool => ProductResource::tenantHasAvailableProductSlots()),

            Actions\Action::make('product_limit_reached')
                ->label('Límite de productos alcanzado')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->visible(fn(): bool => ! ProductResource::tenantHasAvailableProductSlots())
                ->action(function (): void {
                    Notification::make()
                        ->title('No puedes crear más productos')
                        ->body(ProductResource::productLimitMessage())
                        ->warning()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
