<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        return Auth::check();
    }

    public function getColumns(): int | string | array
    {
        return [
            'default' => 1,
            'xl' => 2,
        ];
    }

    public function mount()
    {
        /** @var \Percy\Core\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            return;
        }

        if ($user->hasRole('Vendedor')) {
            if ($user->canAccessRestaurantPos()) {
                return redirect()->to(PosRestaurant::getUrl());
            }

            return redirect()->to(SaleResource::getUrl('index'));
        }
    }
}
