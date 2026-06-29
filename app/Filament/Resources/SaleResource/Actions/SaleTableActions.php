<?php

namespace App\Filament\Resources\SaleResource\Actions;

use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Models\Sale;
use Percy\Core\Services\Sales\CorrelativeService;
use Percy\Core\Services\Inventory\InventoryService;

class SaleTableActions
{
    public static function get(): array
    {
        return [
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
                                app(InventoryService::class)->refundStock(
                                    $item,
                                    (float) $item->quantity,
                                    "Anulación Ticket {$record->series}-{$record->correlative}: {$data['reason']}"
                                );
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
                                $total = round((float) $data['importe_adicional'], 2);

                                $tenantIgv = $record->tenant->igv_percentage ?? 18;
                                $factorDivisor = 1 + ($tenantIgv / 100);

                                // Calculamos la base igual como SUNAT espera: total / factor
                                $base = round($total / $factorDivisor, 2);

                                // El IGV debe cerrar contra el total, no recalcularse aparte
                                $igv = round($total - $base, 2);

                                $nota->op_gravadas = $base;
                                $nota->igv = $igv;
                                $nota->total = $total;
                                $nota->op_exoneradas = 0;
                                $nota->op_inafectas = 0;

                                $nota->save();

                                // En lugar de clonar todos los ítems, creamos UNO SOLO con el cargo extra
                                $producto = \Percy\Core\Models\Product::find($data['product_id']);

                                $nota->items()->create([
                                    'tenant_id' => $record->tenant_id,
                                    'product_id' => $producto->id,
                                    'item_name' => $producto->name . ' - ' . $nota->cancel_reason_description,
                                    'quantity' => 1,
                                    'unit_price' => $total,
                                    'unit_value' => $base,
                                    'igv_amount' => $igv,
                                    'total' => $total,
                                    'afectacion_igv_id' => $producto->afectacion_igv_id ?? 1,
                                    'unit_code' => $producto->unidadSunat ? $producto->unidadSunat->codigo : 'NIU',
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
        ];
    }
}
