<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Filament\Resources\TenantResource\RelationManagers;
use Percy\Core\Models\Tenant;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\TenantResource\Schemas\TenantForm;
use App\Filament\Resources\TenantResource\Tables\TenantTable;
use Filament\Infolists\Infolist;
use App\Filament\Resources\TenantResource\Schemas\TenantInfolist;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Súper Admin';
    protected static ?string $navigationLabel = 'Clientes SaaS';
    protected static ?string $modelLabel = 'Cliente SaaS';
    protected static ?string $pluralModelLabel = 'Clientes SaaS';
    protected static ?int $navigationSort = 1;
    /**
     * ¿Quién puede ver este menú en la barra lateral?
     * Solo el "Dueño del SaaS" (Aquel que NO pertenece a ningún negocio: tenant_id = null)
     */
    public static function canViewAny(): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return TenantForm::configure($form);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return TenantInfolist::configure($infolist);
    }

    public static function table(Table $table): Table
    {
        return TenantTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'businessSector',
                'plan',
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
