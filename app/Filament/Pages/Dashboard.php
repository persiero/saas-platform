<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard';
    
    protected static string $view = 'filament.pages.dashboard';
    
    public function getMaxContentWidth(): ?string
    {
        return '7xl';
    }
}
