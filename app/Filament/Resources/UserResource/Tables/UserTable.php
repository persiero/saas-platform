<?php

namespace App\Filament\Resources\UserResource\Tables;

use App\Filament\Resources\UserResource\Actions\UserTableActions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class UserTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query->with(['tenant', 'roles']))
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->recordUrl(null)
            ->recordAction('view')
            ->columns(self::columns())
            ->filters(self::filters())
            ->filtersFormColumns([
                'default' => 1,
                'md' => 2,
                'xl' => 3,
            ])
            ->actions([
                Tables\Actions\ActionGroup::make(UserTableActions::actions())
                    ->label('Acciones')
                    ->tooltip('Acciones')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
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
            Tables\Columns\TextColumn::make('mobile_summary')
                ->label('Usuario')
                ->state(fn(User $record): string => $record->name)
                ->description(function (User $record): string {
                    $email = $record->email;
                    $roles = $record->roles->pluck('name')->join(', ') ?: 'Sin rol';
                    $estado = $record->is_active ? 'Activo' : 'Desactivado';

                    return "{$email} · {$roles} · {$estado}";
                })
                ->icon('heroicon-o-user')
                ->weight('black')
                ->wrap()
                ->searchable(['name', 'email'])
                ->hiddenFrom('md'),

            Tables\Columns\TextColumn::make('name')
                ->label('Usuario')
                ->searchable()
                ->sortable()
                ->weight('medium')
                ->icon('heroicon-o-user')
                ->visibleFrom('md'),

            Tables\Columns\TextColumn::make('email')
                ->label('Correo electrónico')
                ->searchable()
                ->copyable()
                ->copyMessage('Correo copiado')
                ->visibleFrom('lg'),

            Tables\Columns\TextColumn::make('tenant.name')
                ->label('Empresa')
                ->badge()
                ->color(fn($state): string => match ($state) {
                    null => 'danger',
                    default => 'success',
                })
                ->default('SÚPER ADMIN')
                ->sortable()
                ->searchable()
                ->visible(fn(): bool => Auth::user()?->isSuperAdmin() ?? false)
                ->visibleFrom('lg'),

            Tables\Columns\TextColumn::make('roles.name')
                ->label('Roles')
                ->badge()
                ->color('info')
                ->separator(',')
                ->placeholder('Sin rol')
                ->visibleFrom('md'),

            Tables\Columns\IconColumn::make('is_active')
                ->label('Activo')
                ->boolean()
                ->sortable()
                ->trueIcon('heroicon-o-check-circle')
                ->falseIcon('heroicon-o-x-circle')
                ->trueColor('success')
                ->falseColor('danger')
                ->tooltip(
                    fn(Model $record): string =>
                    $record->is_active
                        ? 'Usuario activo'
                        : 'Usuario desactivado'
                )
                ->visibleFrom('md'),

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
                ->visible(fn(): bool => Auth::user()?->isSuperAdmin() ?? false),
        ];
    }
}
