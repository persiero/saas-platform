<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class QuickActions extends Widget
{
    protected static string $view = 'filament.widgets.quick-actions';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return Auth::check() && Auth::user()?->tenant_id !== null;
    }
}
