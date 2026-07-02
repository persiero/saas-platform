<?php

namespace App\Filament\Resources\TenantResource\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Percy\Core\Models\Tenant;
use App\Filament\Resources\TenantResource\Actions\TenantTableActions;

class TenantTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->filters(self::filters())
            ->actions(self::actions())
            ->bulkActions([])
            ->emptyStateHeading('Aún no tienes clientes')
            ->emptyStateDescription('Registra tu primer cliente SaaS para empezar.')
            ->emptyStateIcon('heroicon-o-server-stack');
    }

    private static function columns(): array
    {
        return [
            Tables\Columns\ImageColumn::make('logo')
                ->label('Logo')
                ->disk('r2_public')
                ->circular()
                ->size(40)
                ->toggleable(),

            Tables\Columns\TextColumn::make('name')
                ->label('Negocio')
                ->searchable()
                ->sortable()
                ->weight('bold')
                ->icon('heroicon-o-building-storefront')
                ->description(fn(Tenant $record): ?string => $record->business_name),

            Tables\Columns\TextColumn::make('businessSector.name')
                ->label('Giro')
                ->badge()
                ->color('info')
                ->sortable(),

            Tables\Columns\TextColumn::make('plan.name')
                ->label('Plan')
                ->badge()
                ->color(fn(?string $state): string => match ($state) {
                    'Básico' => 'gray',
                    'Estándar' => 'info',
                    'Premium' => 'success',
                    default => 'warning',
                })
                ->placeholder('Sin plan'),

            Tables\Columns\TextColumn::make('ruc')
                ->label('RUC')
                ->searchable()
                ->copyable()
                ->placeholder('Sin RUC'),

            Tables\Columns\IconColumn::make('sunat_certificate')
                ->label('Cert. SUNAT')
                ->boolean()
                ->state(fn(Tenant $record): bool => ! empty($record->sunat_certificate))
                ->trueIcon('heroicon-o-check-badge')
                ->falseIcon('heroicon-o-x-circle')
                ->trueColor('success')
                ->falseColor('danger'),

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
                ),

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
        ];
    }

    private static function actions(): array
    {
        return [
            Tables\Actions\ActionGroup::make(TenantTableActions::actions())
                ->label('Acciones')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button()
                ->color('gray'),
        ];
    }
}
