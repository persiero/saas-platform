<?php

namespace App\Filament\Resources\TenantResource\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Percy\Core\Models\Tenant;
use App\Filament\Resources\TenantResource\Actions\TenantTableActions;
use Illuminate\Database\Eloquent\Builder;

class TenantTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query->with(['businessSector', 'plan']))
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
            ->actions(self::actions())
            ->bulkActions([])
            ->emptyStateHeading('Aún no tienes clientes')
            ->emptyStateDescription('Registra tu primer cliente SaaS para empezar.')
            ->emptyStateIcon('heroicon-o-server-stack');
    }

    private static function columns(): array
    {
        return [
            Tables\Columns\TextColumn::make('mobile_summary')
                ->label('Cliente SaaS')
                ->state(fn(Tenant $record): string => $record->name)
                ->description(function (Tenant $record): string {
                    $plan = $record->plan?->name ?? 'Sin plan';
                    $sector = $record->businessSector?->name ?? 'Sin giro';
                    $estado = $record->is_active ? 'Activo' : 'Suspendido';

                    return "{$plan} · {$sector} · {$estado}";
                })
                ->icon('heroicon-o-building-storefront')
                ->weight('black')
                ->wrap()
                ->searchable(['name', 'business_name', 'ruc', 'domain'])
                ->hiddenFrom('md'),

            Tables\Columns\ImageColumn::make('logo')
                ->label('Logo')
                ->disk('r2_public')
                ->circular()
                ->size(40)
                ->toggleable()
                ->visibleFrom('lg'),

            Tables\Columns\TextColumn::make('name')
                ->label('Negocio')
                ->searchable()
                ->sortable()
                ->weight('bold')
                ->icon('heroicon-o-building-storefront')
                ->description(fn(Tenant $record): ?string => $record->business_name)
                ->visibleFrom('md'),

            Tables\Columns\TextColumn::make('businessSector.name')
                ->label('Giro')
                ->badge()
                ->color('info')
                ->sortable()
                ->visibleFrom('lg'),

            Tables\Columns\TextColumn::make('plan.name')
                ->label('Plan')
                ->badge()
                ->color(fn(?string $state): string => match ($state) {
                    'Básico' => 'gray',
                    'Estándar' => 'info',
                    'Premium' => 'success',
                    default => 'warning',
                })
                ->placeholder('Sin plan')
                ->visibleFrom('md'),

            Tables\Columns\TextColumn::make('ruc')
                ->label('RUC')
                ->searchable()
                ->copyable()
                ->placeholder('Sin RUC')
                ->visibleFrom('xl'),

            Tables\Columns\IconColumn::make('sunat_certificate')
                ->label('Cert. SUNAT')
                ->boolean()
                ->state(fn(Tenant $record): bool => ! empty($record->sunat_certificate))
                ->trueIcon('heroicon-o-check-badge')
                ->falseIcon('heroicon-o-x-circle')
                ->trueColor('success')
                ->falseColor('danger')
                ->visibleFrom('xl'),

            Tables\Columns\IconColumn::make('is_active')
                ->label('Acceso')
                ->boolean()
                ->sortable()
                ->trueIcon('heroicon-o-check-circle')
                ->falseIcon('heroicon-o-x-circle')
                ->trueColor('success')
                ->falseColor('danger')
                ->tooltip(
                    fn(Tenant $record): string =>
                    $record->is_active
                        ? 'Negocio activo'
                        : 'Negocio suspendido'
                )
                ->visibleFrom('md'),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Creado')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    private static function filters(): array
    {
        return [
            Tables\Filters\TernaryFilter::make('is_active')
                ->label('Estado de Acceso')
                ->placeholder('Todos')
                ->trueLabel('Activos')
                ->falseLabel('Suspendidos')
                ->native(false),

            Tables\Filters\SelectFilter::make('plan_id')
                ->label('Plan')
                ->relationship('plan', 'name')
                ->searchable()
                ->preload(),

            Tables\Filters\SelectFilter::make('business_sector_id')
                ->label('Giro')
                ->relationship('businessSector', 'name')
                ->searchable()
                ->preload(),
        ];
    }

    private static function actions(): array
    {
        return [
            Tables\Actions\ActionGroup::make(TenantTableActions::actions())
                ->label('Acciones')
                ->tooltip('Acciones')
                ->icon('heroicon-m-ellipsis-vertical')
                ->iconButton()
                ->color('gray'),
        ];
    }
}
