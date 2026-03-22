<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use Percy\Core\Models\Product;
use Percy\Core\Models\InventoryMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Actions\RestoreBulkAction;
use Filament\Tables\Actions\ForceDeleteBulkAction;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Productos';
    protected static ?string $modelLabel = 'Producto';
    protected static ?string $pluralModelLabel = 'Productos';
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?int $navigationSort = 22;

    // Filtro global para asegurar que solo se vean productos del tenant actual
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id) // 1. Mantiene tu filtro de seguridad SaaS
            ->with(['category']) // 2. Soluciona el error N+1 (Carga ansiosa)
            ->withoutGlobalScopes([
                SoftDeletingScope::class, // 3. Permite que la papelera (TrashedFilter) funcione correctamente
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
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Información Principal')->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Producto / Servicio')
                            ->required()
                            ->maxLength(150),

                        Forms\Components\TextInput::make('barcode')
                            ->label('Código de Barras')
                            ->prefixIcon('heroicon-o-qr-code')
                            ->placeholder('Escanea o digita el código')
                            ->unique(ignoreRecord: true), // Evita que dos productos tengan el mismo código

                        Forms\Components\Select::make('unidad_sunat_id')
                            ->relationship('unidadSunat', 'descripcion')
                            ->label('Unidad de Medida (SUNAT)')
                            ->required()
                            ->default(1) // Por defecto NIU (Id 1)
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('type')
                            ->label('Tipo de Ítem')
                            ->options([
                                'product' => 'Producto Físico',
                                'service' => 'Servicio',
                            ])
                            ->required()
                            ->default('product'),

                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name') // Solo mostrará categorías de ESTE tenant
                            ->label('Categoría')
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('description')
                            ->label('Descripción')
                            ->maxLength(255),
                    ])->columns(2),

                    Forms\Components\Section::make('Datos Farmacéuticos')
                        // ESTA LÍNEA ES LA MAGIA: Solo se muestra si el sector del Tenant actual es "Farmacia"
                        ->visible(function () {
                            $sector = Auth::user()->tenant->businessSector->name ?? '';
                            return str_contains(strtolower($sector), 'farmacia') || str_contains(strtolower($sector), 'botica');
                        })
                        ->schema([
                            Forms\Components\TextInput::make('active_ingredient')
                                ->label('Principio Activo (Genérico)'),

                            Forms\Components\TextInput::make('laboratory')
                                ->label('Laboratorio'),

                            Forms\Components\Toggle::make('requires_prescription')
                                ->label('Requiere Receta Médica'),
                        ]),

                    Forms\Components\Section::make('Configuración de Venta Fraccionada')
                        ->description('Define si este producto se puede vender por blíster o por unidad suelta.')
                        // SOLO PARA FARMACIAS/BOTICAS
                        ->visible(function () {
                            $sector = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->name ?? '';
                            return str_contains(strtolower($sector), 'farmacia') || str_contains(strtolower($sector), 'botica');
                        })
                        ->schema([
                            Forms\Components\Toggle::make('is_fractionable')
                                ->label('¿Permitir venta por fracción (Pastillas/Blísteres)?')
                                ->live() // Esto es vital: Hace que la pantalla reaccione instantáneamente al hacer clic
                                ->columnSpanFull(),

                            // Este Grid SOLO aparece si el Toggle de arriba está encendido (true)
                            Forms\Components\Grid::make(3)
                                ->visible(fn (Forms\Get $get) => $get('is_fractionable'))
                                ->schema([
                                    Forms\Components\TextInput::make('units_per_box')
                                        ->label('Total de pastillas por Caja')
                                        ->numeric()
                                        ->required()
                                        ->helperText('Ej: 100'),

                                    Forms\Components\TextInput::make('units_per_blister')
                                        ->label('Pastillas por Blíster')
                                        ->numeric()
                                        ->helperText('Opcional. Ej: 10'),

                                    Forms\Components\TextInput::make('unit_price')
                                        ->label('Precio por Pastilla (Unidad)')
                                        ->numeric()
                                        ->prefix('S/')
                                        ->required()
                                        ->helperText('Precio de venta al menudeo.'),
                                ]),
                        ]),

                ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Precios e Impuestos')->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Precio de Venta')
                            ->numeric()
                            ->prefix('S/')
                            ->required(),

                        Forms\Components\TextInput::make('cost')
                            ->label('Costo Referencial')
                            ->numeric()
                            ->prefix('S/')
                            ->default(0),

                        Forms\Components\Select::make('afectacion_igv_id')
                            ->relationship('afectacionIgv', 'descripcion')
                            ->label('Afectación IGV')
                            ->required()
                            ->default(1) // Por defecto Gravado (Id 1)
                            ->searchable()
                            ->preload(),

                    ]),
                ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3); // Divide la pantalla en 3 columnas para un diseño más pro
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with('category'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-cube')
                    ->description(fn (Product $record): ?string => $record->category?->name),

                Tables\Columns\TextColumn::make('barcode')
                    ->label('Cód. Barras')
                    ->icon('heroicon-o-qr-code')
                    ->searchable() // ¡Esto automáticamente agrega la búsqueda general por código!
                    ->sortable()
                    ->copyable() // UX Extra: Permite copiar el código con un clic
                    ->copyMessage('Código copiado')
                    ->placeholder('Sin código')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Precio')
                    ->money('PEN')
                    ->sortable()
                    ->weight('black') // Más grueso para que resalte
                    ->color('primary') // Le da el color de tu marca (Azul/Verde/Naranja)
                    ->size('lg'),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stock')
                    ->numeric()
                    ->badge()
                    ->state(function (Product $record): float {
                        $record->refresh();
                        return $record->current_stock;
                    })
                    ->icon(fn (float $state): string => match (true) {
                        $state <= 5 => 'heroicon-o-exclamation-triangle',
                        $state <= 15 => 'heroicon-o-exclamation-circle',
                        default => 'heroicon-o-check-circle',
                    })
                    ->color(fn (float $state): string => match (true) {
                        $state <= 5 => 'danger',
                        $state <= 15 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('unidadSunat.codigo')
                    ->label('Unidad')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\ToggleColumn::make('active')
                    ->label('Activo')
                    ->sortable(),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Categoría')
                    ->relationship('category', 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos')
                    ->native(false),

                Tables\Filters\TernaryFilter::make('has_barcode')
                    ->label('¿Tiene Cód. Barras?')
                    ->placeholder('Todos los productos')
                    ->trueLabel('Con código')
                    ->falseLabel('Sin código')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('barcode'),
                        false: fn (Builder $query) => $query->whereNull('barcode'),
                    ),

                Tables\Filters\Filter::make('low_stock')
                    ->label('Stock bajo')
                    ->query(fn (Builder $query): Builder => $query->whereHas('inventoryMovements', function ($q) {
                        // Filtro personalizado para stock bajo
                    }))
                    ->toggle(),

                TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    // 1. NUEVO BOTÓN: Para que el Cajero pueda consultar el producto sin modificarlo
                    Tables\Actions\ViewAction::make()
                        ->label('Ver detalles')
                        ->icon('heroicon-o-eye')
                        ->color('info'),

                    // 2. BOTÓN EDITAR (Filament lo oculta solo gracias a canEdit)
                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil')
                        ->color('warning'),

                    Tables\Actions\DeleteAction::make() // Borrado lógico
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Producto')
                        ->modalDescription('¿Estás seguro de que deseas eliminar este producto? Esta acción no se puede deshacer.'),

                    Tables\Actions\RestoreAction::make()
                        ->label('Restaurar')
                        ->icon('heroicon-o-arrow-uturn-left') // Icono de "Deshacer"
                        ->color('success') // Color verde positivo
                        ->requiresConfirmation()
                        ->modalHeading('Restaurar Producto')
                        ->modalDescription('¿Deseas rescatar este producto de la papelera? Volverá a estar visible y activo en el sistema.'),

                    // 3. BOTÓN AJUSTE DE INVENTARIO: Protegido solo para el Admin y optimizado para Farmacias
                    Tables\Actions\Action::make('manual_adjustment')
                        ->label('Ajuste de Inventario')
                        ->icon('heroicon-o-scale')
                        ->color('warning')
                        ->visible(function () {
                            /** @var \Percy\Core\Models\User $user */
                            $user = \Illuminate\Support\Facades\Auth::user();
                            return $user->isAdmin();
                        })
                        ->form(function ($record) {
                            // Detectamos si el usuario pertenece a una Farmacia/Botica
                            $sector = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->name ?? '';
                            $isPharmacy = str_contains(strtolower($sector), 'farmacia') || str_contains(strtolower($sector), 'botica');

                            return [
                                Forms\Components\Select::make('type')
                                    ->label('Motivo del Ajuste')
                                    ->options([
                                        'OUT' => 'Salida (Merma, Vencimiento, Rotura)',
                                        'IN' => 'Ingreso (Inventario Inicial, Sobrante)',
                                    ])
                                    ->required()
                                    ->default('OUT')
                                    ->live(),

                                // 🌟 NUEVO: Selección de Lote (Solo visible para Farmacias)
                                Forms\Components\Select::make('product_batch_id')
                                    ->label('Lote a afectar')
                                    ->options(function () use ($record) {
                                        return \Percy\Core\Models\ProductBatch::where('product_id', $record->id)
                                            ->get()
                                            ->mapWithKeys(function ($b) {
                                                $vence = $b->expiration_date ? \Carbon\Carbon::parse($b->expiration_date)->format('d/m/Y') : 'N/D';
                                                return [$b->id => "Lote: {$b->batch_number} | Vence: {$vence} | Stock Actual: " . (float)$b->current_quantity];
                                            });
                                    })
                                    ->visible(fn () => $isPharmacy)
                                    ->required(fn () => $isPharmacy)
                                    ->searchable()
                                    ->preload(),

                                // 🌟 NUEVO: Unidad de Ajuste (Solo si es farmacia Y el producto es fraccionable)
                                Forms\Components\Select::make('measurement_unit')
                                    ->label('Unidad de Ajuste')
                                    ->options([
                                        'box' => 'Caja Entera',
                                        'unit' => 'Unidad Suelta (Pastilla/Blíster)',
                                    ])
                                    ->visible(fn () => $isPharmacy && $record->is_fractionable && $record->units_per_box > 0)
                                    ->required(fn () => $isPharmacy && $record->is_fractionable && $record->units_per_box > 0)
                                    ->default('box')
                                    ->live(),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->required()
                                    ->live(),

                                Forms\Components\Placeholder::make('stock_warning')
                                    ->label('')
                                    ->content(function (Forms\Get $get, $record) use ($isPharmacy) {
                                        if ($get('type') === 'OUT' && $get('quantity')) {
                                            $stockActual = (float) $record->current_stock;
                                            $cantidadIngresada = (float) $get('quantity');

                                            // Matemática rápida para el aviso visual
                                            $cantidadAjuste = $cantidadIngresada;
                                            if ($isPharmacy && $record->is_fractionable && $get('measurement_unit') === 'unit' && $record->units_per_box > 0) {
                                                $cantidadAjuste = $cantidadIngresada / $record->units_per_box;
                                            }

                                            $stockFinal = $stockActual - $cantidadAjuste;

                                            if ($stockFinal < 0) {
                                                return "⚠️ Stock Global insuficiente. Actual: {$stockActual} | Faltarían: " . abs($stockFinal);
                                            }
                                            return "✓ Stock Global actual: {$stockActual} | Quedará en: {$stockFinal}";
                                        }
                                        return '';
                                    })
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'OUT'),

                                Forms\Components\TextInput::make('reason')
                                    ->label('Detalle / Observación')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Ej: 3 pastillas vencidas, 1 caja rota...'),
                            ];
                        })
                        ->action(function (array $data, $record) {
                            $stockActual = (float) $record->current_stock;
                            $cantidadIngresada = abs((float) $data['quantity']);
                            $tipoAjuste = $data['type'];

                            // Detectamos el negocio
                            $sector = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->name ?? '';
                            $isPharmacy = str_contains(strtolower($sector), 'farmacia') || str_contains(strtolower($sector), 'botica');

                            // 1. MATEMÁTICA DE FRACCIONES
                            $cantidadAjuste = $cantidadIngresada;
                            if ($isPharmacy && $record->is_fractionable && ($data['measurement_unit'] ?? null) === 'unit' && $record->units_per_box > 0) {
                                $cantidadAjuste = $cantidadIngresada / $record->units_per_box;
                            }

                            // 2. BUSCAR EL LOTE AFECTADO
                            $batch = null;
                            if ($isPharmacy && isset($data['product_batch_id'])) {
                                $batch = \Percy\Core\Models\ProductBatch::find($data['product_batch_id']);
                            }

                            // 3. VALIDACIONES DE STOCK NEGATIVO (Global y Lote)
                            if ($tipoAjuste === 'OUT') {
                                if ($stockActual < $cantidadAjuste) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Stock Global Insuficiente')
                                        ->body("Intentas retirar {$cantidadAjuste} pero solo hay {$stockActual}.")
                                        ->danger()->send();
                                    return;
                                }
                                if ($batch && $batch->current_quantity < $cantidadAjuste) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Stock de Lote Insuficiente')
                                        ->body("El lote seleccionado solo tiene {$batch->current_quantity} disponible.")
                                        ->danger()->send();
                                    return;
                                }
                            }

                            // 4. CALCULAR SALDOS
                            $saldoFinal = $tipoAjuste === 'OUT' ? $stockActual - $cantidadAjuste : $stockActual + $cantidadAjuste;

                            // 5. AFECTAR EL LOTE
                            if ($batch) {
                                $batch->current_quantity = $tipoAjuste === 'OUT'
                                    ? $batch->current_quantity - $cantidadAjuste
                                    : $batch->current_quantity + $cantidadAjuste;
                                $batch->save();
                            }

                            // 6. CREAR REGISTRO EN KARDEX (Inyectando el Lote)
                            \Percy\Core\Models\InventoryMovement::create([
                                'tenant_id' => \Illuminate\Support\Facades\Auth::user()->tenant_id,
                                'product_id' => $record->id,
                                'product_batch_id' => $batch ? $batch->id : null, // 🌟 GUARDAMOS EL LOTE
                                'user_id' => \Illuminate\Support\Facades\Auth::id(),
                                'type' => $tipoAjuste,
                                'quantity' => $cantidadAjuste,
                                'reason' => $data['reason'],
                                'balance_after' => $saldoFinal,
                            ]);

                            // 7. ACTUALIZAR PRODUCTO PRINCIPAL
                            $record->update(['current_stock' => $saldoFinal]);

                            \Filament\Notifications\Notification::make()
                                ->title('Inventario Ajustado')
                                ->body('El Kardex y el stock han sido actualizados correctamente.')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        // 🌟 Título dinámico con el nombre del producto
                        ->modalHeading(fn ($record) => 'Ajuste de Inventario: ' . $record->name)
                        // 🌟 Descripción extra para darle más seguridad al usuario
                        ->modalDescription(fn ($record) => new \Illuminate\Support\HtmlString(
                            'Estás a punto de modificar el stock de <strong>' . $record->name . '</strong>. <br>Stock global actual: <strong>' . (float)$record->current_stock . '</strong>'
                        ))
                        ->modalWidth('lg'),
                ])
                ->label('Acciones')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button()
                ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Productos')
                        ->modalDescription('¿Estás seguro de que deseas eliminar los productos seleccionados?'),
                    Tables\Actions\RestoreBulkAction::make(), // 🌟 Restaurar varios a la vez
                ]),
            ])
            ->emptyStateHeading('Sin productos registrados')
            ->emptyStateDescription('Comienza agregando tu primer producto o servicio')
            ->emptyStateIcon('heroicon-o-cube');
    }

    public static function getRelations(): array
    {
        $relations = [];

        // MAGIA DEL SAAS: Condicionamos qué módulos se encienden según el giro del negocio.
        // Verificamos si el negocio actual tiene el giro de "Farmacia" o "Botica".
        $sector = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->name ?? '';

        if (str_contains(strtolower($sector), 'farmacia') || str_contains(strtolower($sector), 'botica')) {
            $relations[] = RelationManagers\BatchesRelationManager::class;
        }

        return $relations;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }


}
