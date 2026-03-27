<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'batches';

    protected static ?string $title = 'Lotes y Fechas de Vencimiento';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('batch_number')
                    ->label('Número de Lote')
                    // 🌟 OCULTAMOS EL CAMPO PARA MINIMARKET
                    ->visible(function () {
                        $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                        return $features['has_lots'] ?? false;
                    })
                    // 🌟 DEJA DE SER OBLIGATORIO PARA MINIMARKET
                    ->required(function () {
                        $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                        return $features['has_lots'] ?? false;
                    })
                    ->maxLength(255)
                    ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                Forms\Components\DatePicker::make('manufacturing_date')
                    ->label('Fecha de Fabricación')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(now())
                    // 🌟 UX: Ocultamos esto en minimarket también para que el formulario sea súper limpio
                    ->visible(function () {
                        $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                        return $features['has_lots'] ?? false;
                    }),

                Forms\Components\DatePicker::make('expiration_date')
                    ->label('Fecha de Vencimiento') // 🌟 Le quité "(DIGEMID)" para que sirva tanto para medicinas como abarrotes
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->minDate(now()), // No puede estar vencido al registrarlo

                Forms\Components\TextInput::make('initial_quantity')
                    ->label('Cantidad Ingresada')
                    ->required()
                    ->numeric()
                    // 🌟 1. Límites y saltos dinámicos leyendo al Producto "Padre"
                    ->step(fn (\Filament\Resources\RelationManagers\RelationManager $livewire) => $livewire->getOwnerRecord()->is_weighable ? 0.001 : 1)
                    ->minValue(fn (\Filament\Resources\RelationManagers\RelationManager $livewire) => $livewire->getOwnerRecord()->is_weighable ? 0.001 : 1)
                    ->default(1)
                    // 🌟 2. UX: Añadimos el sufijo visual (Kg, Lt, Und) para guiar al usuario
                    ->suffix(function (\Filament\Resources\RelationManagers\RelationManager $livewire) {
                        $product = $livewire->getOwnerRecord();
                        if (!$product->is_weighable) return 'Und';

                        $code = $product->unidadSunat?->codigo ?? 'NIU';
                        return match($code) {
                            'KGM' => 'Kg',
                            'LTR' => 'Lt',
                            'GLL' => 'Gal',
                            default => 'Und',
                        };
                    })
                    // 🌟 3. BLINDAJE: Impide forzar decimales si el producto es por unidad
                    ->rules([
                        fn (\Filament\Resources\RelationManagers\RelationManager $livewire) => function (string $attribute, $value, \Closure $fail) use ($livewire) {
                            $product = $livewire->getOwnerRecord();
                            if (!$product->is_weighable && fmod((float)$value, 1) !== 0.0) {
                                $fail('Este producto solo admite cantidades enteras.');
                            }
                        },
                    ])
                    ->disabledOn('edit')
                    ->dehydrated(),

                Forms\Components\Hidden::make('current_quantity')
                    ->default(fn (Forms\Get $get) => $get('initial_quantity')),

                Forms\Components\Hidden::make('tenant_id')
                    ->default(fn () => Auth::user()->tenant_id),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('batch_number')
            ->columns([
                Tables\Columns\TextColumn::make('batch_number')
                    ->label('Lote')
                    ->searchable()
                    ->weight('bold')
                    // 🌟 Solo visible si el negocio usa lotes (Farmacia)
                    ->visible(fn () => \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features['has_lots'] ?? false),

                Tables\Columns\TextColumn::make('expiration_date')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    // Lógica mágica de colores para alertar a la farmacia:
                    ->color(function ($state): string {
                        if (!$state) return 'gray';
                        $fecha = Carbon::parse($state);
                        if ($fecha->isPast()) return 'danger'; // Vencido
                        if ($fecha->diffInDays(now()) <= 90) return 'warning'; // Vence en 3 meses
                        return 'success'; // Todo bien
                    }),

                Tables\Columns\TextColumn::make('current_quantity')
                    ->label('Stock Actual')
                    ->numeric()
                    ->sortable()
                    // Si el stock es bajo, lo marcamos en rojo
                    ->color(fn ($state): string => $state <= 5 ? 'danger' : 'gray'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(fn () => (\Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features['has_lots'] ?? false) ? 'Registrar Lote' : 'Registrar Vencimiento')
                    ->icon('heroicon-o-plus-circle')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['current_quantity'] = $data['initial_quantity'];
                        $data['tenant_id'] = Auth::user()->tenant_id;

                        // 🌟 MAGIA PARA MINIMARKET: Si el lote viene vacío (porque está oculto), generamos uno interno
                        if (empty($data['batch_number'])) {
                            $data['batch_number'] = 'VENC-' . now()->format('dmy-His');
                        } else {
                            $data['batch_number'] = strtoupper($data['batch_number']);
                        }

                        return $data;
                    })
                    ->after(function (\Illuminate\Database\Eloquent\Model $record) {
                        // ... (Mantén toda tu lógica del Kardex intacta aquí) ...
                        $product = $record->product;
                        $qtyIngresada = $record->initial_quantity;
                        $stockRealExacto = $product->batches()->where('is_active', true)->sum('current_quantity');
                        $product->current_stock = $stockRealExacto;
                        $product->save();

                        \Percy\Core\Models\InventoryMovement::create([
                            'tenant_id'        => $record->tenant_id,
                            'product_id'       => $record->product_id,
                            'product_batch_id' => $record->id,
                            'user_id'          => \Illuminate\Support\Facades\Auth::id(),
                            'type'             => 'IN',
                            'quantity'         => $qtyIngresada,
                            'balance_after'    => $product->current_stock,
                            'reason'           => 'Registro Manual de Stock/Vencimiento',
                        ]);
                    }),
            ])
            ->actions([
                // 🌟 NUEVA ACCIÓN DE EDITAR (Restringida y Segura)
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->color('warning')
                    // 🚀 Sobrescribimos el formulario solo para la edición
                    ->form([
                        Forms\Components\TextInput::make('batch_number')
                            ->label('Número de Lote')
                            ->maxLength(255)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->visible(function () {
                                $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                                return $features['has_lots'] ?? false;
                            })
                            ->required(function () {
                                $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                                return $features['has_lots'] ?? false;
                            }),

                        Forms\Components\DatePicker::make('manufacturing_date')
                            ->label('Fecha de Fabricación')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now()),

                        Forms\Components\DatePicker::make('expiration_date')
                            ->label('Fecha de Vencimiento')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            // Ojo: Aquí quité el minDate(now()) porque si se equivocaron
                            // y el producto ya venció ayer, necesitan poder registrar la fecha real.
                            ->visible(function () {
                                $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                                return $features['has_expiry_dates'] ?? false;
                            })
                            ->required(function () {
                                $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
                                return $features['has_expiry_dates'] ?? false;
                            }),

                        // 🌟 BLINDAJE DE KARDEX: Mostramos el stock, pero BLOQUEADO
                        Forms\Components\TextInput::make('current_quantity')
                            ->label('Stock Actual')
                            ->numeric()
                            ->disabled() // El usuario no puede hacer clic ni escribir aquí
                            ->dehydrated(false) // Le dice a Filament: "Ignora este campo al guardar en BD"
                            ->helperText('El stock no se puede modificar manualmente por seguridad del Kardex.'),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        // Aseguramos que si editan el lote, se guarde en mayúsculas
                        if (isset($data['batch_number'])) {
                            $data['batch_number'] = strtoupper($data['batch_number']);
                        }
                        return $data;
                    }),

                Tables\Actions\DeleteAction::make()
                    ->before(function (\Illuminate\Database\Eloquent\Model $record) {
                        // $record es el Lote que estamos a punto de borrar
                        $product = $record->product;
                        $qtyToDeduct = $record->current_quantity; // Lo que quedaba vivo en este lote

                        // 1. Restamos la mercancía del stock global
                        $product->current_stock -= $qtyToDeduct;
                        $product->save();

                        // 2. Dejamos la huella en el Kardex explicando por qué desapareció el stock
                        \Percy\Core\Models\InventoryMovement::create([
                            'tenant_id'        => $record->tenant_id,
                            'product_id'       => $record->product_id,
                            'user_id'          => \Illuminate\Support\Facades\Auth::id(),
                            'type'             => 'OUT',
                            'quantity'         => $qtyToDeduct,
                            'balance_after'    => $product->current_stock,
                            'reason'           => 'Eliminación manual de Lote: ' . $record->batch_number,
                        ]);
                    }),
            ])
            ->bulkActions([
                //Tables\Actions\BulkActionGroup::make([
                  //  Tables\Actions\DeleteBulkAction::make(),
                //]),
            ]);
    }
}
