<?php

namespace App\Filament\Resources\UserResource\Tables;

use App\Filament\Resources\UserResource\Actions\UserTableActions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UserTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->filters(self::filters())
            ->actions([
                Tables\Actions\ActionGroup::make(UserTableActions::actions())
                    ->label('Acciones')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->button()
                    ->color('gray'),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No hay usuarios registrados')
            ->emptyStateDescription('Crea usuarios para administrar el acceso al sistema.')
            ->emptyStateIcon('heroicon-o-users');
    }

    private static function columns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')
                ->label('Usuario')
                ->searchable()
                ->sortable()
                ->weight('medium')
                ->icon('heroicon-o-user'),

            Tables\Columns\TextColumn::make('email')
                ->label('Correo electrónico')
                ->searchable()
                ->copyable()
                ->copyMessage('Correo copiado'),

            Tables\Columns\TextColumn::make('tenant.name')
                ->label('Empresa')
                ->badge()
                ->color(fn ($state): string => match ($state) {
                    null => 'danger',
                    default => 'success',
                })
                ->default('SÚPER ADMIN')
                ->sortable()
                ->searchable()
                ->visible(fn (): bool => Auth::user()?->isSuperAdmin() ?? false),

            Tables\Columns\TextColumn::make('roles.name')
                ->label('Roles')
                ->badge()
                ->color('info')
                ->separator(',')
                ->placeholder('Sin rol'),

            Tables\Columns\IconColumn::make('is_active')
                ->label('Activo')
                ->boolean()
                ->sortable()
                ->trueIcon('heroicon-o-check-circle')
                ->falseIcon('heroicon-o-x-circle')
                ->trueColor('success')
                ->falseColor('danger')
                ->tooltip(fn (Model $record): string =>
                    $record->is_active
                        ? 'Usuario activo'
                        : 'Usuario desactivado'
                ),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Fecha de creación')
                ->date('d/m/Y')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    private static function filters(): array
    {
        return [
            Tables\Filters\TernaryFilter::make('is_active')
                ->label('Estado del usuario')
                ->placeholder('Todos')
                ->trueLabel('Activos')
                ->falseLabel('Desactivados')
                ->native(false),

            Tables\Filters\SelectFilter::make('roles')
                ->label('Rol')
                ->relationship('roles', 'name')
                ->searchable()
                ->preload()
                ->multiple(),

            Tables\Filters\SelectFilter::make('tenant_id')
                ->label('Empresa')
                ->relationship('tenant', 'name')
                ->searchable()
                ->preload()
                ->visible(fn (): bool => Auth::user()?->isSuperAdmin() ?? false),
        ];
    }
}
