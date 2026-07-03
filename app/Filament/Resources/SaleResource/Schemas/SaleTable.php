<?php

namespace App\Filament\Resources\SaleResource\Schemas;

use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Percy\Core\Models\Sale;
use App\Filament\Resources\SaleResource;
use App\Filament\Resources\SaleResource\Actions\SaleTableActions;

class SaleTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->recordUrl(fn(Sale $record): string => SaleResource::getUrl('view', ['record' => $record]))
            ->recordClasses(fn(Sale $record): ?string => match (true) {
                $record->channel === 'ecommerce' && $record->status === 'pending_payment' => 'bg-warning-50/60 dark:bg-warning-900/10',
                $record->status === 'canceled' => 'bg-danger-50/50 dark:bg-danger-900/10 opacity-75',
                default => null,
            })
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->emptyStateHeading('Sin ventas registradas')
            ->emptyStateDescription('Cuando registres ventas o recibas pedidos web, aparecerán en este listado.')
            ->columns([
                Tables\Columns\TextColumn::make('mobile_summary')
                    ->label('Venta')
                    ->state(fn(Sale $record): string => self::documentNumber($record))
                    ->description(function (Sale $record): string {
                        $cliente = $record->channel === 'ecommerce'
                            ? (self::extractWebNote($record, 'Nombre') ?: 'Cliente Web')
                            : ($record->customer?->name ?? 'Público en General');

                        $estado = match (true) {
                            $record->channel === 'ecommerce' && $record->status === 'pending_payment' => 'Pendiente Web',
                            $record->status === 'canceled' => 'Anulado',
                            $record->status === 'completed' => 'Completado',
                            default => ucfirst($record->status ?? 'Sin estado'),
                        };

                        return $cliente
                            . ' · S/ ' . number_format((float) $record->total, 2)
                            . ' · ' . $estado
                            . ' · ' . $record->sold_at?->format('d/m/Y H:i');
                    })
                    ->icon(fn(Sale $record): string => match (true) {
                        $record->channel === 'ecommerce' && $record->status === 'pending_payment' => 'heroicon-o-clock',
                        $record->channel === 'ecommerce' => 'heroicon-o-globe-alt',
                        $record->status === 'canceled' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-shopping-bag',
                    })
                    ->color(fn(Sale $record): string => match (true) {
                        $record->channel === 'ecommerce' && $record->status === 'pending_payment' => 'warning',
                        $record->channel === 'ecommerce' => 'info',
                        $record->status === 'canceled' => 'danger',
                        default => 'gray',
                    })
                    ->weight('black')
                    ->wrap()
                    ->searchable(['series', 'correlative'])
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('document_number')
                    ->label('Comprobante')
                    ->state(fn(Sale $record): string => "{$record->series}-{$record->correlative}")
                    ->searchable(['series', 'correlative'])
                    ->sortable(false)
                    ->weight('bold')
                    // 🌟 CAMBIO: Evaluamos primero si está anulado
                    ->icon(fn(Sale $record): string => match (true) {
                        $record->channel === 'ecommerce' && $record->status === 'pending_payment' => 'heroicon-o-clock',
                        $record->status === 'canceled' => 'heroicon-o-x-circle',
                        $record->document_type === '01' => 'heroicon-o-document-text',
                        $record->document_type === '03' => 'heroicon-o-receipt-percent',
                        $record->document_type === '07' => 'heroicon-o-arrow-uturn-left',
                        $record->document_type === '08' => 'heroicon-o-arrow-trending-up',
                        default => 'heroicon-o-document',
                    })
                    // 🌟 CAMBIO: Color rojo si está anulado
                    ->color(fn(Sale $record): string => match (true) {
                        $record->channel === 'ecommerce' && $record->status === 'pending_payment' => 'warning',
                        $record->status === 'canceled' => 'danger',
                        $record->document_type === '07' => 'danger',
                        $record->document_type === '08' => 'warning',
                        $record->document_type === '01' => 'info',
                        $record->document_type === '03' => 'success',
                        default => 'gray',
                    })
                    // 🌟 MAGIA VISUAL AQUÍ: Agregamos la validación para Facturas y Boletas
                    ->description(fn(Sale $record): ?string => match (true) {
                        $record->channel === 'ecommerce' && $record->status === 'pending_payment' => 'Pedido Web Pendiente',
                        $record->status === 'canceled' => 'Anulado',
                        in_array($record->document_type, ['07', '08']) => "Ref: {$record->affected_document_series}-{$record->affected_document_correlative}",

                        $record->document_type === '01' => $record->affected_document_series ? "Factura (Ex {$record->affected_document_series}-{$record->affected_document_correlative})" : 'Factura',
                        $record->document_type === '03' => $record->affected_document_series ? "Boleta (Ex {$record->affected_document_series}-{$record->affected_document_correlative})" : 'Boleta',

                        $record->document_type === '00' => 'Nota de Venta',
                        default => null,
                    })
                    ->visibleFrom('md'),

                // 🌟 1. COLUMNA CLIENTE (Omnicanal Corregido)
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->state(function (Sale $record): string {
                        if ($record->channel === 'ecommerce') {
                            return self::extractWebNote($record, 'Nombre') ?: 'Cliente Web';
                        }

                        return $record->customer?->name ?? 'Público en General';
                    })
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
                    ->description(function (Sale $record): ?string {
                        if ($record->channel === 'ecommerce') {
                            $phone = self::extractWebNote($record, 'Celular');

                            return $phone
                                ? '🌐 Tienda Online | 📱 ' . $phone
                                : '🌐 Tienda Online';
                        }

                        return '🏪 Venta en Local';
                    })
                    ->limit(30)
                    ->tooltip(function (Sale $record): ?string {
                        if ($record->channel === 'ecommerce') {
                            return self::extractWebNote($record, 'Nombre') ?: 'Cliente Web';
                        }

                        return $record->customer?->name ?? 'Público en General';
                    })
                    ->visibleFrom('md'),

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
                        $isPaid = $record->status === 'completed';

                        $cajeroNombre = $record->user ? explode(' ', $record->user->name)[0] : 'Desconocido';

                        if (!empty($record->table_id)) {
                            return $isPaid ? "💰 Cobró: {$cajeroNombre}" : '⏳ Comiendo en mesa';
                        }

                        if ($record->channel === 'ecommerce') {
                            if ($record->status === 'pending_payment') {
                                return '⏳ Pendiente de procesar';
                            }

                            if ($record->status === 'canceled') {
                                return '❌ Pedido cancelado';
                            }

                            return "✅ Atendido por: {$cajeroNombre}";
                        }

                        return null;
                    })
                    ->limit(20)
                    ->toggleable()
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('2xl'),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable()
                    ->weight('black')
                    ->alignment('right')
                    ->size('lg')
                    ->description(function (Sale $record): string {
                        $payment = $record->payment_method ?: 'Pendiente';

                        return 'Pago: ' . $payment;
                    })
                    ->color(fn(Sale $record): string => match (true) {
                        $record->status === 'canceled' => 'danger',
                        $record->channel === 'ecommerce' && $record->status === 'pending_payment' => 'warning',
                        default => 'gray',
                    })
                    ->visibleFrom('md'),

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
                    ->toggleable()
                    ->visibleFrom('2xl'),

                Tables\Columns\TextColumn::make('sunat_status')
                    ->label('Estado')
                    ->badge()
                    ->icon(fn(?string $state, Sale $record): string => match (true) {
                        $record->channel === 'ecommerce' && $record->status === 'pending_payment' => 'heroicon-o-clock',
                        $record->status === 'canceled' => 'heroicon-o-archive-box-x-mark',
                        $record->document_type === '00' => 'heroicon-o-building-storefront',
                        $state === 'accepted' => 'heroicon-o-check-circle',
                        $state === 'pending' => 'heroicon-o-clock',
                        $state === 'rejected' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn(?string $state, Sale $record): string => match (true) {
                        $record->channel === 'ecommerce' && $record->status === 'pending_payment' => 'warning',
                        $record->status === 'canceled' => 'danger',
                        $record->document_type === '00' => 'gray',
                        $state === 'accepted' => 'success',
                        $state === 'pending' => 'warning',
                        $state === 'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(?string $state, Sale $record): string => match (true) {
                        $record->channel === 'ecommerce' && $record->status === 'pending_payment' => 'Pendiente Web',
                        $record->status === 'canceled' => 'Anulado',
                        $record->document_type === '00' => 'Uso Interno',
                        $state === 'accepted' => 'Aceptado',
                        $state === 'pending' => 'Pendiente SUNAT',
                        $state === 'rejected' => 'Rechazado SUNAT',
                        default => $state ? ucfirst($state) : 'Sin estado',
                    })
                    ->tooltip(
                        fn(Sale $record): ?string =>
                        $record->sent_at
                            ? "Enviado: {$record->sent_at->format('d/m/Y H:i')}"
                            : $record->sunat_description
                    )
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('sold_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->icon('heroicon-o-calendar')
                    ->since()
                    ->tooltip(fn(Sale $record): string => $record->sold_at->format('d/m/Y H:i:s'))
                    ->visibleFrom('lg'),
            ])
            ->defaultSort('sold_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('web_pending')
                    ->label('Pedidos web por procesar')
                    ->query(
                        fn(Builder $query): Builder =>
                        $query->where('channel', 'ecommerce')
                            ->where('status', 'pending_payment')
                    ),

                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Tipo')
                    ->options([
                        '00' => 'Notas de Venta',
                        '01' => 'Facturas',
                        '03' => 'Boletas',
                        '07' => 'Notas de Crédito',
                        '08' => 'Notas de Débito',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado de venta')
                    ->options([
                        'pending_payment' => 'Pendiente Web',
                        'completed' => 'Completada',
                        'canceled' => 'Anulada',
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
            ->filtersFormWidth('lg')
            ->filtersFormColumns(2)
            ->actions(SaleTableActions::get())
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function documentNumber(Sale $record): string
    {
        $series = $record->series ?: 'WEB';
        $correlative = $record->correlative ?: 'Pendiente';

        return "{$series}-{$correlative}";
    }

    private static function extractWebNote(Sale $record, string $label): ?string
    {
        if (! $record->kitchen_notes) {
            return null;
        }

        $pattern = '/^' . preg_quote($label, '/') . ':\s*(.+)$/mi';

        if (preg_match($pattern, $record->kitchen_notes, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
