<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected static ?string $title = 'Categorías';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva Categoría')
                ->icon('heroicon-o-plus-circle')
                ->modalHeading('Crear Categoría') // Título del modal
                ->slideOver(), // ¡OPCIONAL! Si usas slideOver(), se abrirá un panel lateral en lugar de un cuadro en el centro. ¡Se ve muy pro!
        ];
    }
}
