<?php

namespace App\Filament\Resources\SaleResource\Schemas;

use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Models\Sale;
use Percy\Core\Services\Sales\CorrelativeService;

class SaleTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label('Comprobante')
                    ->state(fn(Sale $record): string => "{$record->series}-{$record->correlative}")
                    ->searchable(['series', 'correlative'])
                    ->sortable(false)
                    ->weight('bold')
                    // 🌟 CAMBIO: Evaluamos primero si está anulado
                    ->icon(fn(Sale $record): string => match (true) {
                        $record->status === 'canceled' => 'heroicon-o-x-circle', // Ícono de anulación
                        $record->document_type === '01' => 'heroicon-o-document-text',
                        $record->document_type === '03' => 'heroicon-o-receipt-percent',
                        $record->document_type === '07' => 'heroicon-o-arrow-uturn-left',
                        $record->document_type === '08' => 'heroicon-o-arrow-trending-up',
                        default => 'heroicon-o-document',
                    })
                    // 🌟 CAMBIO: Color rojo si está anulado
                    ->color(fn(Sale $record): string => match (true) {
                        $record->status === 'canceled' => 'danger',
                        $record->document_type === '07' => 'danger',
                        $record->document_type === '08' => 'warning',
                        $record->document_type === '01' => 'info',
                        $record->document_type === '03' => 'success',
                        default => 'gray',
                    })
                    // 🌟 MAGIA VISUAL AQUÍ: Agregamos la validación para Facturas y Boletas
                    ->description(fn(Sale $record): ?string => match (true) {
                        $record->status === 'canceled' => 'Anulado',
                        in_array($record->document_type, ['07', '08']) => "Ref: {$record->affected_document_series}-{$record->affected_document_correlative}",

                        // Si es Factura o Boleta Y tiene un documento afectado (la nota original), lo mostramos:
                        $record->document_type === '01' => $record->affected_document_series ? "Factura (Ex {$record->affected_document_series}-{$record->affected_document_correlative})" : 'Factura',
                        $record->document_type === '03' => $record->affected_document_series ? "Boleta (Ex {$record->affected_document_series}-{$record->affected_document_correlative})" : 'Boleta',

                        $record->document_type === '00' => 'Nota de Venta',
                        default => null,
                    }),

                // 🌟 1. COLUMNA CLIENTE (Omnicanal Corregido)
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->default('Público en General') // 🌟 CAMBIO CLAVE: Usamos default en lugar de placeholder
                    ->sortable()
                    ->searchable()
                    ->icon(fn(Sale $record): string => match ($record->channel) {
                        'ecommerce' => 'heroicon-s-globe-alt',
                        default => 'heroicon-o-building-storefront',
                    })
                    ->iconColor(fn(Sale $record): string => match ($record->channel) {
                        'ecommerce' => 'brand',
                        default => 'gray',
                    })
                    ->weight(fn(Sale $record) => $record->channel === 'ecommerce' ? 'bold' : 'normal')
                    ->description(fn(Sale $record): ?string => match ($record->channel) {
                        'ecommerce' => '🌐 Tienda Online',
                        default => '🏪 Venta en Local',
                    })
                    ->limit(30)
                    ->tooltip(fn(Sale $record): ?string => $record->customer?->name ?? 'Público en General'),

                // 🌟 2. COLUMNA ATENCIÓN / COBRO
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Atención / Cobro')
                    ->state(function (Sale $record): string {
                        if ($record->channel === 'ecommerce') return 'Cliente (Web)';
                        return $record->user?->name ?? 'Desconocido';
                    })
                    ->icon(fn(Sale $record) => $record->channel === 'ecommerce' ? 'heroicon-o-shopping-cart' : 'heroicon-o-user')
                    ->weight('bold')
                    ->description(function (Sale $record): ?string {
                        $isPaid = $record->status === 'completed' || !empty($record->payment_method);

                        // 🌟 SOLUCIÓN: Leemos directamente al usuario, no a la caja registradora
                        $cajeroNombre = $record->user ? explode(' ', $record->user->name)[0] : 'Desconocido';

                        // 🍔 CASO 1: RESTAURANTE (Tiene mesa asignada)
                        if (!empty($record->table_id)) {
                            return $isPaid ? "💰 Cobró: {$cajeroNombre}" : '⏳ Comiendo en mesa';
                        }

                        // 🌐 CASO 2: E-COMMERCE (Tienda Web)
                        if ($record->channel === 'ecommerce') {
                            return $isPaid ? "✅ Atendido por: {$cajeroNombre}" : '⏳ Pendiente de atención';
                        }

                        // 🏪 CASO 3: MINIMARKET / PRESENCIAL
                        return null;
                    })
                    ->limit(20)
                    ->toggleable()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable()
                    ->weight('bold')
                    ->alignment('right')
                    ->size('lg'),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Pago')
                    // 🌟 MAGIA UI: Solo se vuelve un "badge" (con fondo) si está Pendiente
                    ->badge(fn(?string $state): bool => $state === 'Pendiente' || empty($state))

                    // Formateamos para que los vacíos digan "Pendiente"
                    ->formatStateUsing(fn(?string $state): string => empty($state) ? 'Pendiente' : $state)

                    // Asignamos los iconos
                    ->icon(fn(?string $state): string => match ($state) {
                        'Efectivo', 'Contado' => 'heroicon-o-banknotes',
                        'Yape', 'Plin' => 'heroicon-o-device-phone-mobile',
                        'Tarjeta' => 'heroicon-o-credit-card',
                        'Transferencia' => 'heroicon-o-arrow-path',
                        null, '', 'Pendiente' => 'heroicon-o-clock',
                        default => 'heroicon-o-currency-dollar',
                    })

                    // 🌟 COLORES CORPORATIVOS (Se aplicarán al icono y al texto, sin fondo saturado)
                    ->color(fn(?string $state): string => match ($state) {
                        'Efectivo', 'Contado' => 'success', // Verde sutil
                        'Yape' => 'purple',                 // Morado característico
                        'Plin' => 'info',                   // Celeste/Azul característico
                        'Tarjeta' => 'warning',             // Naranja/Amarillo neutral
                        'Transferencia' => 'gray',          // Gris sobrio
                        null, '', 'Pendiente' => 'danger',  // Rojo intenso (Como tiene badge=true, este sí tendrá fondo rojo)
                        default => 'gray',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sunat_status')
                    ->label('Estado')
                    ->badge()
                    ->icon(fn(string $state, Sale $record): string => match (true) {
                        $record->status === 'canceled' => 'heroicon-o-archive-box-x-mark',
                        $record->document_type === '00' => 'heroicon-o-building-storefront',
                        $state === 'accepted' => 'heroicon-o-check-circle',
                        $state === 'pending' => 'heroicon-o-clock',
                        $state === 'rejected' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn(string $state, Sale $record): string => match (true) {
                        $record->status === 'canceled' => 'danger',
                        $record->document_type === '00' => 'gray',
                        $state === 'accepted' => 'success',
                        $state === 'pending' => 'warning',
                        $state === 'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state, Sale $record): string => match (true) {
                        $record->status === 'canceled' => 'Anulado',
                        $record->document_type === '00' => 'Uso Interno',
                        $state === 'accepted' => 'Aceptado',
                        $state === 'pending' => 'Pendiente SUNAT',
                        $state === 'rejected' => 'Rechazado SUNAT',
                        default => ucfirst($state),
                    })
                    ->tooltip(
                        fn(Sale $record): ?string =>
                        $record->sent_at
                            ? "Enviado: {$record->sent_at->format('d/m/Y H:i')}"
                            : $record->sunat_description
                    ),

                Tables\Columns\TextColumn::make('sold_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->icon('heroicon-o-calendar')
                    ->since()
                    ->tooltip(fn(Sale $record): string => $record->sold_at->format('d/m/Y H:i:s')),
            ])
            ->defaultSort('sold_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Tipo')
                    ->options([
                        '01' => 'Facturas',
                        '03' => 'Boletas',
                        '07' => 'Notas de Crédito',
                        '08' => 'Notas de Débito',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('sunat_status')
                    ->label('Estado SUNAT')
                    ->options([
                        'accepted' => 'Aceptado',
                        'pending' => 'Pendiente',
                        'rejected' => 'Rechazado',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Método de Pago')
                    ->options(Sale::PAYMENT_METHODS)
                    ->multiple(),

                Tables\Filters\Filter::make('sold_at')
                    ->form([
                        Forms\Components\DatePicker::make('desde')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'],
                                fn(Builder $query, $date): Builder => $query->whereDate('sold_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'],
                                fn(Builder $query, $date): Builder => $query->whereDate('sold_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators[] = 'Desde: ' . \Carbon\Carbon::parse($data['desde'])->format('d/m/Y');
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators[] = 'Hasta: ' . \Carbon\Carbon::parse($data['hasta'])->format('d/m/Y');
                        }
                        return $indicators;
                    }),
            ])
            ->filtersFormWidth('md')
            ->filtersFormColumns(2)
            ->actions([

                // 🖨️ EL ÚNICO BOTÓN AFUERA: Siempre a la mano para imprimir rápido
                Tables\Actions\Action::make('print')
                    ->label('Ticket')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn(Sale $record): string => route('percy.print.ticket', $record))
                    ->openUrlInNewTab(),

                // 📁 GRUPO DE OPCIONES DESPLEGABLE
                Tables\Actions\ActionGroup::make([
                    // 🌟 BOTÓN EDITAR (Filament lo ocultará automáticamente si canEdit es false)
                    Tables\Actions\EditAction::make()
                        ->label('Procesar Pedido')
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning') // Un color llamativo para el cajero
                        ->visible(fn(\Percy\Core\Models\Sale $record) => $record->channel === 'ecommerce' && $record->status === 'pending_payment')
                        ->using(function (\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model {

                            // A. Inyectamos estado y cajero obligatoriamente
                            $data['status'] = 'completed';
                            $data['user_id'] = \Illuminate\Support\Facades\Auth::id();

                            // B. Lógica SUNAT (Corregida)
                            $tipoDoc = $data['document_type'] ?? $record->document_type;

                            if (in_array($tipoDoc, ['01', '03'])) {
                                $data['sunat_status'] = 'pending';
                            } else {
                                unset($data['sunat_status']);
                            }

                            // C. Generamos el nuevo correlativo
                            $tipoNuevo = $data['document_type'] ?? $record->document_type;
                            $serieNueva = $data['series'] ?? $record->series;

                            if ($record->document_type !== $tipoNuevo || $record->series !== $serieNueva || empty($record->correlative)) {
                                $data['correlative'] = app(CorrelativeService::class)
                                    ->next($record->tenant_id, $tipoNuevo, $serieNueva);
                            }

                            // D. Forzamos el guardado
                            $record->forceFill($data);
                            $record->save();

                            return $record;
                        }),

                    // 1. Ver Detalle (Siempre visible)
                    Tables\Actions\ViewAction::make()
                        ->label('Ver Detalle')
                        ->icon('heroicon-o-eye')
                        ->color('info'),

                    // =========================================================
                    // 🌟 ZONA DE NOTAS DE VENTA (TICKETS INTERNOS)
                    // =========================================================
                    Tables\Actions\Action::make('convertToBoleta')
                        ->label('Convertir a Boleta')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->color('success')
                        ->visible(
                            fn(Sale $record) =>
                            $record->document_type === '00' &&
                                $record->status !== 'canceled' &&
                                !\Illuminate\Support\Facades\Auth::user()->hasRole('Vendedor')
                        )
                        ->form([
                            Forms\Components\Select::make('serie_boleta')
                                ->label('Seleccione la Serie de Boleta')
                                ->options(function () {
                                    return \Percy\Core\Models\Serie::where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id)
                                        ->where('document_type', '03')
                                        ->where('active', true)
                                        ->pluck('serie', 'serie');
                                })
                                ->required(),
                        ])
                        ->action(function (array $data, Sale $record) {
                            $originalDocType = $record->document_type;
                            $originalSeries = $record->series;
                            $originalCorrelative = $record->correlative;

                            try {
                                $nuevoCorrelativo = app(CorrelativeService::class)
                                    ->next($record->tenant_id, '03', $data['serie_boleta']);
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Error')
                                    ->body($e->getMessage())
                                    ->send();

                                return;
                            }

                            $record->update([
                                'document_type' => '03',
                                'series' => $data['serie_boleta'],
                                'correlative' => $nuevoCorrelativo,
                                'sold_at' => now(),
                                'sunat_status' => 'pending',
                                'affected_document_type' => $originalDocType,
                                'affected_document_series' => $originalSeries,
                                'affected_document_correlative' => $originalCorrelative,
                            ]);

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Conversión Exitosa')
                                ->body("El ticket ahora es la Boleta {$data['serie_boleta']}-{$nuevoCorrelativo}.")
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Convertir Ticket a Boleta')
                        ->modalDescription('El documento pasará a ser una Boleta Electrónica. El stock se mantendrá intacto. ¿Deseas continuar?'),

                    Tables\Actions\Action::make('anularTicket')
                        ->label('Anular Ticket')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(
                            fn(Sale $record) =>
                            $record->document_type === '00' &&
                                $record->status !== 'canceled' &&
                                !\Illuminate\Support\Facades\Auth::user()->hasRole('Vendedor')
                        )
                        ->form([
                            Forms\Components\TextInput::make('reason')
                                ->label('Motivo de anulación')
                                ->required()
                                ->maxLength(255),
                        ])
                        ->action(function (array $data, Sale $record) {
                            foreach ($record->items as $item) {
                                $product = $item->product;
                                if (!$product) continue;

                                $quantityToReturn = $item->quantity;
                                if ($product->is_fractionable && $item->measurement_unit === 'unit' && $product->units_per_box > 0) {
                                    $quantityToReturn = $item->quantity / $product->units_per_box;
                                }

                                if ($item->product_batch_id) {
                                    $batch = \Percy\Core\Models\ProductBatch::find($item->product_batch_id);
                                    if ($batch) {
                                        $batch->current_quantity += $quantityToReturn;
                                        $batch->save();
                                    }
                                }

                                $product->current_stock += $quantityToReturn;
                                $product->save();

                                \Percy\Core\Models\InventoryMovement::create([
                                    'tenant_id'        => $record->tenant_id,
                                    'product_id'       => $item->product_id,
                                    'product_batch_id' => $item->product_batch_id,
                                    'user_id'          => \Illuminate\Support\Facades\Auth::id(),
                                    'type'             => 'IN',
                                    'quantity'         => $quantityToReturn,
                                    'balance_after'    => $product->current_stock,
                                    'reason'           => "Anulación Ticket {$record->series}-{$record->correlative}: {$data['reason']}",
                                    'reference_type'   => 'Percy\Core\Models\Sale',
                                    'reference_id'     => $record->id,
                                ]);
                            }

                            $record->update([
                                'status' => 'canceled',
                                'sunat_description' => 'ANULADO INTERNAMENTE: ' . $data['reason']
                            ]);

                            \Filament\Notifications\Notification::make()->success()->title('Ticket Anulado')->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Anular Nota de Venta'),

                    // =========================================================
                    // 🌟 ZONA SUNAT (FACTURAS Y BOLETAS)
                    // =========================================================
                    Tables\Actions\Action::make('sendToSunat')
                        ->label('Enviar a SUNAT')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->visible(fn(Sale $record) => $record->sunat_status !== 'accepted' && $record->document_type !== '00' && $record->status !== 'canceled')
                        ->disabled(fn(Sale $record) => in_array($record->document_type, ['07', '08']) && empty($record->credit_note_type))
                        ->requiresConfirmation()
                        ->action(function (Sale $record) {
                            try {

                                // 🌟 SOLUCIÓN AL LAZY LOADING: Precargamos las relaciones que SUNAT necesita
                                $record->loadMissing('items.afectacionIgv', 'customer');

                                $service = new \Percy\Core\Services\SunatService();
                                $result = $service->processAndSend($record);

                                if ($result->isSuccess()) {
                                    \Filament\Notifications\Notification::make()->title('¡Aceptado por SUNAT!')->success()->send();
                                } else {
                                    \Filament\Notifications\Notification::make()->title('Error SUNAT ' . $result->getError()->getCode())->body($result->getError()->getMessage())->danger()->persistent()->send();
                                }
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                            }
                        }),

                    Tables\Actions\Action::make('downloadXml')
                        ->label('Descargar XML')
                        ->icon('heroicon-o-code-bracket')
                        ->url(fn(Sale $record) => route('sales.download-xml', $record))
                        ->visible(fn(Sale $record) => !empty($record->sunat_xml_path) && $record->status !== 'canceled'),

                    Tables\Actions\Action::make('downloadCdr')
                        ->label('Descargar CDR')
                        ->icon('heroicon-o-archive-box')
                        ->url(fn(Sale $record) => route('sales.download-cdr', $record))
                        ->visible(fn(Sale $record) => !empty($record->sunat_cdr_path) && $record->status !== 'canceled'),

                    Tables\Actions\Action::make('anularVenta')
                        ->label('Nota de Crédito')
                        ->icon('heroicon-o-document-minus')
                        ->color('danger')
                        ->visible(function (Sale $record) {
                            $isAdmin = \Illuminate\Support\Facades\Auth::user()->isAdmin();
                            $isAccepted = $record->sunat_status === 'accepted';
                            $isValidDocument = in_array($record->document_type, ['01', '03']);
                            $isNotCanceled = $record->status !== 'canceled'; // Bloquea si ya está anulada

                            return $isAdmin && $isAccepted && $isValidDocument && $isNotCanceled;
                        })

                        ->form([
                            Forms\Components\Select::make('serie_nota')
                                ->label('Serie de Nota de Crédito')
                                ->options(function (Sale $record) {
                                    // Determinamos el prefijo: Factura -> FC, Boleta -> BC
                                    $prefix = ($record->document_type === '01') ? 'FC' : 'BC';

                                    return \Percy\Core\Models\Serie::where('tenant_id', $record->tenant_id)
                                        ->where('document_type', '07')
                                        ->where('serie', 'like', $prefix . '%')
                                        ->where('active', true)
                                        ->pluck('serie', 'serie');
                                })
                                ->required(),

                            //Forms\Components\TextInput::make('correlativo_nota')
                            //->label('Correlativo de la Nota (Ej: 1)')
                            //->numeric()
                            //->required(),

                            Forms\Components\Select::make('credit_note_type')
                                ->label('Motivo de Anulación')
                                ->options([
                                    '01' => 'Anulación de la operación',
                                    '02' => 'Anulación por error en el RUC',
                                    '03' => 'Corrección por error en la descripción',
                                    '06' => 'Devolución total',
                                    '07' => 'Devolución por ítem',
                                    '10' => 'Otros Conceptos',
                                ])
                                ->default('01')
                                ->required(),
                        ])
                        ->action(function (array $data, Sale $record) {
                            try {
                                $nuevoCorrelativo = app(CorrelativeService::class)
                                    ->next($record->tenant_id, '07', $data['serie_nota']);

                                // Clonamos la venta original pero vaciamos los datos de respuesta SUNAT anteriores
                                $nota = $record->replicate([
                                    'sunat_status',
                                    'sunat_code',
                                    'sunat_description',
                                    'sunat_hash',
                                    'sunat_xml_path',
                                    'sunat_cdr_path',
                                    'sunat_pdf_path',
                                    'legend_text'
                                ]);
                                $nota->document_type = '07';
                                $nota->series = $data['serie_nota'];
                                $nota->correlative = $nuevoCorrelativo; // Asignamos el número automático ✅

                                $nota->status = 'completed';

                                // 3. Vinculamos el documento original (La Boleta/Factura que estamos anulando)
                                $nota->affected_document_type = $record->document_type;
                                $nota->affected_document_series = $record->series;
                                $nota->affected_document_correlative = $record->correlative;
                                $nota->credit_note_type = $data['credit_note_type'];

                                // Definimos la descripción según el código elegido
                                $descripciones = [
                                    '01' => 'Anulación de la operación',
                                    '02' => 'Anulación por error en el RUC',
                                    '03' => 'Corrección por error en la descripción',
                                    '06' => 'Devolución total',
                                    '07' => 'Devolución por ítem',
                                    '10' => 'Otros Conceptos',
                                ];
                                $nota->cancel_reason_description = $descripciones[$data['credit_note_type']];

                                // Guardamos el nuevo registro padre
                                $nota->save();

                                // 4. Clonamos los ítems originales idénticos para que la contabilidad cuadre exacto
                                foreach ($record->items as $item) {
                                    $nuevoItem = $item->replicate(['sale_id']);
                                    $nuevoItem->sale_id = $nota->id;
                                    $nuevoItem->save();
                                }

                                // 5. Enviamos la nueva Nota de Crédito a la SUNAT usando tu Service
                                $service = new \Percy\Core\Services\SunatService();
                                $result = $service->processAndSend($nota);

                                if ($result->isSuccess()) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Nota de Crédito Aceptada')
                                        ->body('Se anuló el comprobante y se devolvió el stock correctamente.')
                                        ->success()
                                        ->send();
                                } else {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Error SUNAT ' . $result->getError()->getCode())
                                        ->body($result->getError()->getMessage())
                                        ->danger()
                                        ->persistent()
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Error Crítico')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('aumentarValor')
                        ->label('Nota de Débito')
                        ->icon('heroicon-o-document-plus')
                        ->color('warning')
                        ->visible(function (Sale $record) {
                            $isAdmin = \Illuminate\Support\Facades\Auth::user()->isAdmin();
                            $isAccepted = $record->sunat_status === 'accepted';
                            $isValidDocument = in_array($record->document_type, ['01', '03']);
                            $isNotCanceled = $record->status !== 'canceled';

                            return $isAdmin && $isAccepted && $isValidDocument && $isNotCanceled;
                        })
                        ->form([
                            Forms\Components\Select::make('serie_nota')
                                ->label('Serie de Nota de Débito (Ej: BD01 o FD01)')
                                ->options(function (Sale $record) {
                                    // Factura -> FD, Boleta -> BD
                                    $prefix = ($record->document_type === '01') ? 'FD' : 'BD';

                                    return \Percy\Core\Models\Serie::where('tenant_id', $record->tenant_id)
                                        ->where('document_type', '08')
                                        ->where('serie', 'like', $prefix . '%')
                                        ->where('active', true)
                                        ->pluck('serie', 'serie');
                                })
                                ->required(),

                            //Forms\Components\TextInput::make('correlativo_nota')
                            //->label('Correlativo de la Nota (Ej: 1)')
                            //->numeric()
                            //->required(),

                            Forms\Components\Select::make('debit_note_type')
                                ->label('Motivo de Débito (SUNAT)')
                                ->options([
                                    '01' => 'Intereses por mora',
                                    '02' => 'Aumento en el valor',
                                    '03' => 'Penalidades/otros conceptos',
                                ])
                                ->default('02')
                                ->required(),

                            // NUEVO: Pedimos el producto (para que cuadre en tu BD) y el monto a cobrar
                            Forms\Components\Select::make('product_id')
                                ->label('Concepto a cobrar')
                                ->options(\Percy\Core\Models\Product::where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id)->pluck('name', 'id'))
                                ->required()
                                ->searchable(),

                            Forms\Components\TextInput::make('importe_adicional')
                                ->label('Importe a Sumar (Inc. IGV)')
                                ->numeric()
                                ->required()
                                ->prefix('S/'),
                        ])
                        ->action(function (array $data, Sale $record) {
                            try {
                                // --- LÓGICA DE CORRELATIVO AUTOMÁTICO ---
                                $nuevoCorrelativo = app(CorrelativeService::class)
                                    ->next($record->tenant_id, '08', $data['serie_nota']);

                                // Clonamos la venta original limpia de estados
                                $nota = $record->replicate([
                                    'sunat_status',
                                    'sunat_code',
                                    'sunat_description',
                                    'sunat_hash',
                                    'sunat_xml_path',
                                    'sunat_cdr_path',
                                    'sunat_pdf_path',
                                    'legend_text'
                                ]);
                                $nota->document_type = '08';
                                $nota->series = $data['serie_nota'];
                                $nota->correlative = $nuevoCorrelativo; // Número automático ✅

                                $nota->status = 'completed';

                                // 3. Vinculamos el documento original
                                $nota->affected_document_type = $record->document_type;
                                $nota->affected_document_series = $record->series;
                                $nota->affected_document_correlative = $record->correlative;
                                $nota->credit_note_type = $data['debit_note_type']; // Usamos la misma columna de BD

                                // Definimos la descripción según el Catálogo 10
                                $descripciones = [
                                    '01' => 'Intereses por mora',
                                    '02' => 'Aumento en el valor',
                                    '03' => 'Penalidades/otros conceptos'
                                ];
                                $nota->cancel_reason_description = $descripciones[$data['debit_note_type']];

                                // NUEVA MATEMÁTICA: Calculamos todo en base al nuevo importe
                                $total = (float) $data['importe_adicional'];
                                $base = $total / 1.18; // Asumiendo que es gravado
                                $igv = $total - $base;

                                $nota->op_gravadas = round($base, 2);
                                $nota->igv = round($igv, 2);
                                $nota->total = round($total, 2);
                                $nota->op_exoneradas = 0;
                                $nota->op_inafectas = 0;

                                $nota->save();

                                // En lugar de clonar todos los ítems, creamos UNO SOLO con el cargo extra
                                $producto = \Percy\Core\Models\Product::find($data['product_id']);

                                $nota->items()->create([
                                    'product_id' => $producto->id,
                                    'item_name' => $producto->name . ' - ' . $nota->cancel_reason_description,
                                    'quantity' => 1,
                                    'unit_price' => round($total, 2),
                                    'unit_value' => round($base, 2),
                                    'igv_amount' => round($igv, 2),
                                    'total' => round($total, 2),
                                    'afectacion_igv_id' => $producto->afectacion_igv_id ?? 1,
                                ]);

                                // 5. Enviamos la Nota de Débito
                                $service = new \Percy\Core\Services\SunatService();
                                $result = $service->processAndSend($nota);

                                if ($result->isSuccess()) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Nota de Débito Aceptada')
                                        ->body('Se generó el comprobante correctamente.')
                                        ->success()
                                        ->send();
                                } else {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Error SUNAT ' . $result->getError()->getCode())
                                        ->body($result->getError()->getMessage())
                                        ->danger()
                                        ->persistent()
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Error Crítico')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                ])
                    ->label('Opciones')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button()
                    ->color('gray'),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
