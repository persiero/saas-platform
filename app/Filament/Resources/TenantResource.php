<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Filament\Resources\TenantResource\RelationManagers;
use Percy\Core\Models\Tenant;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\TenantResource\Schemas\TenantForm;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 🌟 AQUÍ COLOCAMOS LA MINIATURA DEL LOGO
                Tables\Columns\ImageColumn::make('logo')
                    ->label('Logo')
                    ->disk('r2_public')
                    ->circular() // 🌟 Lo hacemos circular porque los logos de empresa se ven mejor así
                    ->size(40)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Negocio')
                    ->searchable()
                    ->weight('bold')
                    ->icon('heroicon-o-building-storefront')
                    ->description(fn (Tenant $record): ?string => $record->business_name), // Razón social debajo

                // Muestra el nombre del sector (Ej: Restaurante, Farmacia)
                Tables\Columns\TextColumn::make('businessSector.name')
                    ->label('Giro')
                    ->badge() // Lo muestra como una etiqueta de color
                    ->color('info'),

                Tables\Columns\TextColumn::make('ruc')
                    ->label('RUC')
                    ->searchable()
                    ->copyable(), // UX: Copia rápida para consultar en SUNAT

                // Indicador visual de si ya subieron certificado
                Tables\Columns\IconColumn::make('sunat_certificate')
                    ->label('Cert. SUNAT')
                    ->boolean()
                    ->state(fn (Tenant $record): bool => !empty($record->sunat_certificate))
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Acceso')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado de Acceso')
                    ->trueLabel('Activos')
                    ->falseLabel('Suspendidos'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Configurar')
                    ->icon('heroicon-o-cog-6-tooth'),
            ])
            ->emptyStateHeading('Aún no tienes clientes')
            ->emptyStateDescription('Registra tu primer cliente SaaS para empezar.')
            ->emptyStateIcon('heroicon-o-server-stack')

            ->bulkActions([]);
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
