<?php

namespace App\Filament\Resources\PurchaseResource\Schemas;

use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Percy\Core\Models\Purchase;

class PurchaseInfolist
{
    public static function configure(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Información de la Compra')
                    ->icon('heroicon-o-shopping-bag')
                    ->description('Datos generales del documento, proveedor y estado.')
                    ->schema([
                        TextEntry::make('supplier.name')
                            ->label('Proveedor')
                            ->icon('heroicon-o-building-office-2')
                            ->weight('black')
                            ->placeholder('Proveedor no registrado')
                            ->columnSpan([
                                'default' => 1,
                                'md' => 2,
                            ]),

                        TextEntry::make('document_number')
                            ->label('N° Documento')
                            ->icon('heroicon-o-hashtag')
                            ->placeholder('Sin documento'),

                        TextEntry::make('purchase_date')
                            ->label('Fecha de compra')
                            ->date('d/m/Y')
                            ->icon('heroicon-o-calendar'),

                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn(?string $state): string => match ($state) {
                                'pending' => 'Pendiente',
                                'completed' => 'Completado',
                                'canceled' => 'Cancelado',
                                default => ucfirst($state ?? 'Sin estado'),
                            })
                            ->color(fn(?string $state): string => match ($state) {
                                'pending' => 'warning',
                                'completed' => 'success',
                                'canceled' => 'danger',
                                default => 'gray',
                            }),

                        TextEntry::make('created_at')
                            ->label('Registrado')
                            ->dateTime('d/m/Y h:i A')
                            ->icon('heroicon-o-clock'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 4,
                    ]),

                Section::make('Detalle de Productos')
                    ->icon('heroicon-o-cube')
                    ->description('Productos incluidos en la compra.')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('product.name')
                                    ->label('Producto')
                                    ->weight('black')
                                    ->placeholder('Producto no disponible')
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 12,
                                        'xl' => 5,
                                    ]),

                                TextEntry::make('batch_number')
                                    ->label('Lote')
                                    ->badge()
                                    ->color('primary')
                                    ->placeholder('No aplica')
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 4,
                                        'xl' => 2,
                                    ]),

                                TextEntry::make('expiration_date')
                                    ->label('Vencimiento')
                                    ->date('d/m/Y')
                                    ->badge()
                                    ->color('warning')
                                    ->placeholder('No aplica')
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 4,
                                        'xl' => 2,
                                    ]),

                                TextEntry::make('quantity')
                                    ->label('Cantidad')
                                    ->formatStateUsing(function ($state): string {
                                        $value = (float) $state;

                                        return number_format($value, $value == floor($value) ? 0 : 3);
                                    })
                                    ->badge()
                                    ->color('gray')
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 4,
                                        'xl' => 1,
                                    ]),

                                TextEntry::make('unit_cost')
                                    ->label('Costo Inc. IGV')
                                    ->money('PEN')
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 6,
                                        'xl' => 1,
                                    ]),

                                TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->money('PEN')
                                    ->weight('black')
                                    ->color('success')
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 6,
                                        'xl' => 1,
                                    ]),
                            ])
                            ->columns([
                                'default' => 1,
                                'md' => 12,
                            ])
                            ->grid(1),
                    ]),

                Section::make('Resumen Financiero')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->money('PEN'),

                        TextEntry::make('igv')
                            ->label('IGV')
                            ->money('PEN'),

                        TextEntry::make('total')
                            ->label('TOTAL DE LA COMPRA')
                            ->money('PEN')
                            ->size(TextEntry\TextEntrySize::Large)
                            ->weight('black')
                            ->color('success'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ]),

                Section::make('Notas')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->visible(fn(Purchase $record): bool => filled($record->notes))
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Observaciones')
                            ->placeholder('Sin observaciones')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible(),
            ]);
    }
}
