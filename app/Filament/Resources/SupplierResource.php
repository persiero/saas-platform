<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use Percy\Core\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?string $modelLabel = 'Proveedor';
    protected static ?string $pluralModelLabel = 'Proveedores';
    protected static ?int $navigationSort = 2;

    /**
     * Oculta el módulo de Reportes para el Súper Admin y para los Cajeros/Vendedores
     */
    public static function canViewAny(): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();

        // 1. tenant_id !== null (Bloquea al Súper Admin para que no exploten las gráficas)
        // 2. isAdmin() (Bloquea a los empleados normales para proteger las finanzas)
        return $user->tenant_id !== null && $user->isAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id) // 1. Filtro SaaS
            // ❌ Quitamos el ->with(['category']) porque estos módulos no lo necesitan
            ->withoutGlobalScopes([
                SoftDeletingScope::class, // 2. Permite ver la papelera
            ]);
    }

    public static function canCreate(): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        return $user->isAdmin();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        return $user->isAdmin();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        return $user->isAdmin();
    }

    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        return $user->isAdmin(); // Solo el Admin puede restaurar
    }

    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    // 5. Restricción general para Bulk Actions (Aplica para eliminar/restaurar masivamente)
    public static function canDeleteAny(): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        return $user->isAdmin();
    }

    public static function canRestoreAny(): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        return $user->isAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // COLUMNA IZQUIERDA (Principal)
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Identidad Comercial')
                        ->description('Datos principales de la empresa')
                        ->icon('heroicon-o-building-storefront')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nombre / Razón Social')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Ej: Distribuidora ABC S.A.C.')
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('ruc')
                                ->label('RUC')
                                ->maxLength(11)
                                ->length(11)
                                ->numeric()
                                ->placeholder('20123456789')
                                ->prefixIcon('heroicon-o-identification')
                                ->columnSpan(2),
                        ])->columns(2),

                    Forms\Components\Section::make('Información de Contacto')
                        ->description('Medios para comunicarte con el proveedor')
                        ->icon('heroicon-o-identification')
                        ->schema([
                            Forms\Components\TextInput::make('phone')
                                ->label('Teléfono')
                                ->tel()
                                ->maxLength(20)
                                ->placeholder('987 654 321')
                                ->prefixIcon('heroicon-o-phone')
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('email')
                                ->label('Correo Electrónico')
                                ->email()
                                ->maxLength(255)
                                ->placeholder('contacto@proveedor.com')
                                ->prefixIcon('heroicon-o-envelope')
                                ->columnSpan(1),

                            Forms\Components\Textarea::make('address')
                                ->label('Dirección')
                                ->rows(3)
                                ->placeholder('Av. Principal 123, Distrito, Provincia, Departamento')
                                ->columnSpanFull(),
                        ])->columns(2),
                    ])->columnSpan(['lg' => 2]),

                    // COLUMNA DERECHA (Barra Lateral)
                    Forms\Components\Group::make()->schema([
                        Forms\Components\Section::make('Estado')
                            ->schema([
                                Forms\Components\Toggle::make('active')
                                    ->label('Proveedor Activo')
                                    ->default(true)
                                    ->helperText('Desactiva si ya no trabajas con este proveedor')
                            ]),
                    ])->columnSpan(['lg' => 1]),
                ])
                ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-building-office-2')
                    ->description(fn (Supplier $record): ?string => $record->ruc ? "RUC: {$record->ruc}" : null),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon('heroicon-o-phone')
                    ->searchable()
                    ->placeholder('Sin teléfono')
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->copyMessageDuration(1500),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->icon('heroicon-o-envelope')
                    ->searchable()
                    ->placeholder('Sin email')
                    ->copyable()
                    ->copyMessage('Email copiado')
                    ->copyMessageDuration(1500)
                    ->limit(30),

                Tables\Columns\TextColumn::make('address')
                    ->label('Dirección')
                    ->icon('heroicon-o-map-pin')
                    ->limit(40)
                    ->searchable()
                    ->placeholder('Sin dirección')
                    ->tooltip(fn (Supplier $record): ?string => $record->address)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\ToggleColumn::make('active')
                    ->label('Activo')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Estado')
                    ->placeholder('Todos los proveedores')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos')
                    ->native(false)
                    ->indicator('Estado'),

                TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Ver detalles')
                        ->icon('heroicon-o-eye')
                        ->color('info'),

                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil')
                        ->color('warning'),

                    Tables\Actions\DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Proveedor')
                        ->modalDescription('¿Estás seguro de que deseas eliminar este proveedor? Esta acción no se puede deshacer.'),

                    Tables\Actions\RestoreAction::make()
                        ->label('Restaurar')
                        ->icon('heroicon-o-arrow-uturn-left') // Icono de "Deshacer"
                        ->color('success') // Color verde positivo
                        ->requiresConfirmation()
                        ->modalHeading('Restaurar Proveedor')
                        ->modalDescription('¿Deseas rescatar este proveedor de la papelera? Volverá a estar visible y activo en el sistema.'),

                ])->label('Acciones')
                  ->icon('heroicon-o-ellipsis-vertical')
                  ->button()
                  ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Proveedores')
                        ->modalDescription('¿Estás seguro de que deseas eliminar los proveedores seleccionados?'),

                    Tables\Actions\RestoreBulkAction::make()
                        ->label('Restaurar seleccionados')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Restaurar Proveedores')
                        ->modalDescription('¿Deseas restaurar los proveedores seleccionados y devolverlos al catálogo activo?'),
                ]),
            ])
            ->emptyStateHeading('No hay proveedores registrados')
            ->emptyStateDescription('Comienza registrando tu primer proveedor usando el botón de arriba.')
            ->emptyStateIcon('heroicon-o-building-office-2');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
