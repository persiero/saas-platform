<?php

namespace App\Filament\Resources\SaleResource\Schemas;

use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Grid;
use App\Filament\Resources\CashRegisterResource;

class SaleInfolist
{
    public static function configure(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // 🌟 CABECERA: Información General y de Restaurante
                Section::make('Información del Comprobante')
                    ->icon('heroicon-o-receipt-percent') // Iconito para que se vea mejor
                    ->schema([
                        TextEntry::make('document_type')
                            ->label('Comprobante')
                            ->formatStateUsing(fn($state) => match ($state) {
                                '01' => 'Factura',
                                '03' => 'Boleta',
                                '07' => 'Nota de Crédito',
                                '08' => 'Nota de Débito',
                                default => 'Nota de Venta', // Es más elegante que mostrar '00'
                            })
                            ->badge()
                            ->color('info'),

                        TextEntry::make('series')
                            ->label('Serie')
                            ->weight('bold'),

                        TextEntry::make('correlative')
                            ->label('Correlativo')
                            ->weight('bold'),

                        // 🌟 NUEVO: Mostramos la Mesa (Si es venta mostrador dirá "Para Llevar / Mostrador")
                        TextEntry::make('table.name')
                            ->label('Ubicación (Mesa)')
                            ->icon('heroicon-o-squares-2x2')
                            ->placeholder('Mostrador / Para llevar')
                            ->badge()
                            ->color('warning'),

                        TextEntry::make('customer.name')
                            ->label('Cliente')
                            ->placeholder('Público en General') // Por si no hay cliente registrado
                            ->icon('heroicon-o-user'),

                        // 🌟 EL CAJERO: Quien cobró (Dueño de la caja donde se pagó)
                        TextEntry::make('cashRegister.user.name')
                            ->label('Cobrado por (Cajero)')
                            ->icon('heroicon-o-banknotes')
                            ->placeholder('No registrado')
                            // Solo mostramos al cajero si la venta ya se pagó y se cerró
                            ->visible(fn($record) => $record->status === 'completed'),

                        // 🌟 EL MOZO: Quien creó la comanda
                        TextEntry::make('user.name')
                            ->label('Atendido por (Mozo)')
                            ->icon('heroicon-o-user')
                            ->weight('bold')
                            ->color('primary'),

                        TextEntry::make('sold_at')
                            ->label('Fecha de Emisión')
                            ->dateTime('d/m/Y h:i A')
                            ->icon('heroicon-o-calendar'),
                    ])->columns(4), // Cambiado a 4 columnas para que no se vea tan apretado

                Section::make('Caja Asociada')
                    ->icon('heroicon-o-calculator')
                    ->visible(fn ($record) => filled($record->cash_register_id))
                    ->schema([
                        TextEntry::make('cashRegister.id')
                            ->label('Caja')
                            ->formatStateUsing(fn ($state) => '#' . $state)
                            ->badge()
                            ->color('primary')
                            ->url(fn ($record) => $record->cash_register_id
                                ? CashRegisterResource::getUrl('view', ['record' => $record->cash_register_id])
                                : null),

                        TextEntry::make('cashRegister.status')
                            ->label('Estado de Caja')
                            ->formatStateUsing(fn ($state) => $state === 'open' ? 'Abierta' : 'Cerrada')
                            ->badge()
                            ->color(fn ($state) => $state === 'open' ? 'success' : 'gray'),

                        TextEntry::make('cashRegister.user.name')
                            ->label('Caja abierta por')
                            ->icon('heroicon-o-user')
                            ->placeholder('No registrado'),

                        TextEntry::make('cashRegister.closedBy.name')
                            ->label('Caja cerrada por')
                            ->icon('heroicon-o-identification')
                            ->placeholder('Aún abierta'),

                        TextEntry::make('cashRegister.opened_at')
                            ->label('Apertura')
                            ->dateTime('d/m/Y h:i A')
                            ->icon('heroicon-o-lock-open'),

                        TextEntry::make('cashRegister.closed_at')
                            ->label('Cierre')
                            ->dateTime('d/m/Y h:i A')
                            ->icon('heroicon-o-lock-closed')
                            ->placeholder('Aún abierta'),
                    ])
                    ->columns(3),

                // 🌟 DETALLE: La Comanda / Ticket
                Section::make('Detalle de Productos')
                    ->icon('heroicon-o-shopping-bag')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('quantity')
                                    ->label('Cant.')
                                    ->badge()
                                    ->color('gray')
                                    // 🌟 MAGIA UX: Formateo inteligente según el tipo de unidad
                                    ->formatStateUsing(function ($state, $record) {
                                        // $state trae el 4.0000, lo convertimos a float para matar ceros inútiles
                                        $cantidad = (float) $state;

                                        // Si se vendió por caja
                                        if ($record->measurement_unit === 'box') {
                                            return number_format($cantidad, 0) . ' Caj';
                                        }

                                        // Evaluamos el código SUNAT que quedó guardado en esta línea
                                        $codigo = $record->unit_code ?? 'NIU';
                                        $esGranel = in_array($codigo, ['KGM', 'LTR', 'GLL', 'GRM']);

                                        $sufijo = match ($codigo) {
                                            'KGM' => 'kg',
                                            'LTR' => 'lt',
                                            'GLL' => 'gal',
                                            'NIU' => 'und',
                                            'ZZ'  => 'serv',
                                            default => strtolower($codigo)
                                        };

                                        // Si es a granel, permitimos decimales (ej. 1.50 kg)
                                        if ($esGranel) {
                                            return number_format($cantidad, 2) . ' ' . $sufijo;
                                        }

                                        // Si es una unidad normal, mostramos números enteros (ej. 4 und)
                                        return number_format($cantidad, 0) . ' ' . $sufijo;
                                    }),

                                // 🌟 CAMBIO: Usamos 'item_name' en lugar de 'product.name'
                                // Esto es vital contablemente: muestra el nombre exacto que tenía el producto en el momento de la venta
                                TextEntry::make('item_name')
                                    ->label('Producto')
                                    ->weight('bold'),

                                TextEntry::make('unit_price')
                                    ->label('Precio Unit.')
                                    ->money('PEN'),

                                TextEntry::make('total')
                                    ->label('Subtotal')
                                    ->money('PEN')
                                    ->color('success')
                                    ->weight('bold'),
                            ])
                            ->columns(4)
                            ->grid(1) // 🌟 IMPORTANTE: Forza a que cada producto ocupe una fila entera, como un recibo
                    ]),

                // 🌟 PIE: Resumen Financiero y Pagos
                Section::make('Resumen Financiero')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        // Parte superior: Impuestos
                        TextEntry::make('op_gravadas')
                            ->label('Op. Gravadas')
                            ->money('PEN'),

                        TextEntry::make('igv')
                            // 🌟 MAGIA SAAS: Leemos el IGV dinámico del Tenant (Empresa)
                            // Usamos (float) para que "18.00" se vea como "18" y "10.50" se vea como "10.5"
                            ->label(function ($record) {
                                $porcentajeIgv = (float) ($record->tenant->igv_percentage ?? 18);
                                return "IGV ({$porcentajeIgv}%)";
                            })
                            ->money('PEN'),

                        // Parte inferior: Totales y Método de Pago
                        Grid::make(3)->schema([
                            TextEntry::make('payment_method')
                                ->label('Método de Pago')
                                ->badge()
                                ->color('primary'),

                            TextEntry::make('payment_reference')
                                ->label('N° Referencia / Operación')
                                ->placeholder('No aplica')
                                ->visible(fn($record) => in_array($record->payment_method, ['Yape', 'Plin', 'Tarjeta', 'Transferencia'])),

                            TextEntry::make('total')
                                ->label('IMPORTE TOTAL PAGADO')
                                ->money('PEN')
                                ->size(TextEntry\TextEntrySize::Large)
                                ->weight('black')
                                ->color('success')
                                ->columnSpan(fn($record) => in_array($record->payment_method, ['Efectivo']) ? 2 : 1),
                        ]),
                    ])->columns(2),
            ]);
    }
}
