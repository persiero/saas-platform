<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseResource\Pages;
use Percy\Core\Models\Purchase;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use App\Filament\Resources\PurchaseResource\Tables\PurchaseTable;
use App\Filament\Resources\PurchaseResource\Schemas\PurchaseForm;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?string $modelLabel = 'Compra';
    protected static ?string $pluralModelLabel = 'Compras';
    protected static ?int $navigationSort = 1;


    public static function canViewAny(): bool
    {
        return Auth::user()?->canViewPurchases() ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->canCreatePurchases() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        if (! Auth::user()?->canEditPurchases()) {
            return false;
        }

        if ($record instanceof Purchase && $record->status !== 'pending') {
            return false;
        }

        return true;
    }

    public static function canDelete(Model $record): bool
    {
        if (! Auth::user()?->canDeletePurchases()) {
            return false;
        }

        if ($record instanceof Purchase && $record->status !== 'pending') {
            return false;
        }

        return true;
    }

    public static function canDeleteAny(): bool
    {
        return Auth::user()?->canDeletePurchases() ?? false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()
            ->with(['supplier']);

        if (! $user?->isSuperAdmin()) {
            $query->where('tenant_id', $user?->tenant_id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return PurchaseForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return PurchaseTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchases::route('/'),
            'create' => Pages\CreatePurchase::route('/create'),
            'edit' => Pages\EditPurchase::route('/{record}/edit'),
        ];
    }
}
