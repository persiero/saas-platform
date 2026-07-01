<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\UserResource\Tables\UserTable;
use App\Filament\Resources\UserResource\Schemas\UserForm;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $modelLabel = 'Usuario';
    protected static ?string $pluralModelLabel = 'Usuarios';
    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = Auth::user();

        if (! $user?->isAdmin()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return (int) $record->tenant_id === (int) $user->tenant_id;
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
        return UserForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return UserTable::configure($table);
    }

    // EL NUEVO ESCUDO: Solo ves los usuarios de tu propio negocio
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // <-- Cambiado en esta línea:
        if (Auth::check() && Auth::user()->tenant_id) {
            $query->where('tenant_id', Auth::user()->tenant_id);
        }

        return $query;
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
            'index' => Pages\ListUsers::route('/'),
            //'create' => Pages\CreateUser::route('/create'),
            //'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
