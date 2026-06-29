<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        return true;
    }

    public function mount()
    {
        /** @var \Percy\Core\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
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
