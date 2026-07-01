<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function beforeCreate(): void
    {
        if (UserResource::tenantHasAvailableUserSlots()) {
            return;
        }

        Notification::make()
            ->title('Límite de usuarios alcanzado')
            ->body(UserResource::userLimitMessage())
            ->warning()
            ->persistent()
            ->send();

        throw ValidationException::withMessages([
            'limit' => UserResource::userLimitMessage(),
        ]);
    }
}
