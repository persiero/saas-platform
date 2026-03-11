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
                    ->required()
                    ->maxLength(255)
                    ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                Forms\Components\DatePicker::make('manufacturing_date')
                    ->label('Fecha de Fabricación')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(now()), // No puede ser en el futuro

                Forms\Components\DatePicker::make('expiration_date')
                    ->label('Fecha de Vencimiento (DIGEMID)')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->minDate(now()), // No puede estar vencido al registrarlo

                Forms\Components\TextInput::make('initial_quantity')
                    ->label('Cantidad Ingresada')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    // MAGIA: Editable al crear, bloqueado al editar
                    ->disabledOn('edit')
                    ->dehydrated(), // Asegura que el valor se envíe aunque esté bloqueado

                // El stock actual inicia igual a la cantidad ingresada
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
                    ->weight('bold'),

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
                    ->label('Registrar Lote')
                    ->icon('heroicon-o-plus-circle')
                    // Mutamos los datos antes de crear para asegurar que current = initial
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['current_quantity'] = $data['initial_quantity'];
                        $data['tenant_id'] = Auth::user()->tenant_id;
                        $data['batch_number'] = strtoupper($data['batch_number']);
                        return $data;
                    })
                    // 🌟 LA MAGIA SEGURA: Actualizamos el stock global contando los lotes reales
                    ->after(function (\Illuminate\Database\Eloquent\Model $record) {
                        $product = $record->product;
                        $qtyIngresada = $record->initial_quantity;

                        // 1. CÁLCULO ABSOLUTO (A prueba de dobles sumas)
                        // Sumamos el stock exacto de todos los lotes de este producto
                        $stockRealExacto = $product->batches()->where('is_active', true)->sum('current_quantity');

                        $product->current_stock = $stockRealExacto;
                        $product->save();

                        // 2. Dejamos la huella en el Kardex Universal
                        \Percy\Core\Models\InventoryMovement::create([
                            'tenant_id'        => $record->tenant_id,
                            'product_id'       => $record->product_id,
                            'product_batch_id' => $record->id,
                            'user_id'          => \Illuminate\Support\Facades\Auth::id(),
                            'type'             => 'IN',
                            'quantity'         => $qtyIngresada,
                            'balance_after'    => $product->current_stock, // Ahora el Kardex reflejará el número exacto
                            'reason'           => 'Registro Manual de Lote',
                        ]);
                    }),
            ])
            ->actions([
                //Tables\Actions\EditAction::make(),
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
