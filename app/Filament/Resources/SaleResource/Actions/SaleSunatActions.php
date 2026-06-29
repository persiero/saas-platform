<?php

namespace App\Filament\Resources\SaleResource\Actions;

use Filament\Forms;
use Filament\Tables;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Models\Sale;
use Percy\Core\Services\Sales\SaleNoteService;
use Percy\Core\Services\SunatService;

class SaleSunatActions
{
    public static function get(): array
    {
        return [
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
                        $nota = app(SaleNoteService::class)->createCreditNote(
                            $record,
                            $data['serie_nota'],
                            $data['credit_note_type']
                        );

                        $result = app(SunatService::class)->processAndSend($nota);

                        if ($result->isSuccess()) {
                            Notification::make()
                                ->title('Nota de Crédito Aceptada')
                                ->body('Se anuló el comprobante y se devolvió el stock correctamente.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Error SUNAT ' . $result->getError()->getCode())
                                ->body($result->getError()->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
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
                        $nota = app(SaleNoteService::class)->createDebitNote(
                            $record,
                            $data['serie_nota'],
                            $data['debit_note_type'],
                            (int) $data['product_id'],
                            (float) $data['importe_adicional']
                        );

                        $result = app(SunatService::class)->processAndSend($nota);

                        if ($result->isSuccess()) {
                            Notification::make()
                                ->title('Nota de Débito Aceptada')
                                ->body('Se generó el comprobante correctamente.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Error SUNAT ' . $result->getError()->getCode())
                                ->body($result->getError()->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error Crítico')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
