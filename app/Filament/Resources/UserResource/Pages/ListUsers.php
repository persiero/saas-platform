<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Control de Usuarios';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo Usuario')
                ->icon('heroicon-o-user-plus')
                ->slideOver()
                ->modalWidth('2xl')
                ->modalHeading('Registrar Usuario')
                ->createAnother(false)
                ->visible(fn(): bool => UserResource::tenantHasAvailableUserSlots()),

            Actions\Action::make('user_limit_reached')
                ->label('Límite de usuarios alcanzado')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->visible(fn(): bool => ! UserResource::tenantHasAvailableUserSlots())
                ->action(function (): void {
                    Notification::make()
                        ->title('No puedes crear más usuarios')
                        ->body(UserResource::userLimitMessage())
                        ->warning()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
