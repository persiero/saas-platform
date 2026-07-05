<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Percy\Core\Models\ProductBatch;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ExpiringBatchesWidget extends BaseWidget
{
    // Hacemos que ocupe todo el ancho de la pantalla (1 columna entera)
    protected int | string | array $columnSpan = [
        'default' => 'full',
        'xl' => 1,
    ];

    // Le damos prioridad para que aparezca arriba, justo debajo de los botones
    protected static ?int $sort = 6;

    // ¡EL ESCUDO DEL SAAS! Solo las farmacias pueden ver este widget
    public static function canView(): bool
    {
        $user = Auth::user();

        if (! $user || ! $user->tenant || ! $user->tenant->businessSector) {
            return false;
        }

        $features = $user->tenant->businessSector->features ?? [];

        return (bool) ($features['has_expiry_dates'] ?? false);
    }

    public function table(Table $table): Table
    {
        // 🌟 1. Obtenemos el sector del negocio para hacer la tabla inteligente
        $sector = Auth::user()->tenant->businessSector->slug ?? 'general';
        $features = Auth::user()->tenant->businessSector->features ?? [];

        return $table
            ->query(
                // Buscamos los lotes del negocio actual que tengan stock y venzan en los próximos 90 días (o ya estén vencidos)
                ProductBatch::query()
                    ->with('product.unidadSunat') // Traemos el producto y de paso, su Unidad de SUNAT
                    ->where('tenant_id', Auth::user()->tenant_id)
                    ->where('current_quantity', '>', 0) // No alertamos si ya se acabó el stock
                    ->where('expiration_date', '<=', now()->addDays(90))
                    ->orderBy('expiration_date', 'asc') // Los más urgentes arriba
            )
            ->heading('🚨 Alerta de Vencimientos (Próximos 90 días)')
            ->description('Productos que requieren rotación urgente o retiro de los estantes.')

            // 🌟 2. Hacemos el texto del estado vacío dinámico
            ->emptyStateHeading('¡Todo en orden!')
            ->emptyStateDescription($sector === 'farmacia'
                ? 'No hay medicamentos próximos a vencer en los siguientes 90 días.'
                : 'No hay productos próximos a vencer en los siguientes 90 días.')
            ->emptyStateIcon('heroicon-o-check-badge')
            // ------------------------------------------

            ->columns([
                Tables\Columns\TextColumn::make('mobile_summary')
                    ->label($sector === 'farmacia' ? 'Medicamento' : 'Producto')
                    ->state(fn(ProductBatch $record): string => $record->product?->name ?? 'Producto no disponible')
                    ->description(function (ProductBatch $record) use ($features): string {
                        $expirationDate = Carbon::parse($record->expiration_date);
                        $estado = $expirationDate->isPast()
                            ? 'Vencido'
                            : 'Vence ' . $expirationDate->format('d/m/Y');

                        $lote = ($features['has_lots'] ?? false) && $record->batch_number
                            ? "Lote: {$record->batch_number}"
                            : 'Sin lote';

                        $stock = self::formatBatchStock($record);

                        return "{$estado} · {$lote} · Stock: {$stock}";
                    })
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color(fn(ProductBatch $record): string => Carbon::parse($record->expiration_date)->isPast() ? 'danger' : 'warning')
                    ->weight('black')
                    ->wrap()
                    ->searchable(['batch_number'])
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('product.name')
                    // 🌟 3. Etiqueta dinámica: Medicamento para farmacias, Producto para el resto
                    ->label($sector === 'farmacia' ? 'Medicamento' : 'Producto')
                    ->weight('bold')
                    ->searchable()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('batch_number')
                    ->label('Lote')
                    ->badge()
                    ->color('gray')
                    // 🌟 4. LA MAGIA: Ocultamos la columna si es minimarket
                    // (o mejor aún, si su JSON de features dice que NO usan lotes)
                    ->hidden(fn() => !($features['has_lots'] ?? false))
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('expiration_date')
                    ->label('Fecha de Vencimiento')
                    ->date('d/m/Y')
                    ->badge()
                    ->color(function ($state): string {
                        if (Carbon::parse($state)->isPast()) return 'danger'; // Rojo si ya venció
                        return 'warning'; // Amarillo/Naranja si está por vencer
                    })
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('current_quantity')
                    ->label('Stock Atrapado')
                    ->color(fn($state) => $state > 10 ? 'danger' : 'gray')
                    ->formatStateUsing(fn($state, ProductBatch $record): string => self::formatBatchStock($record))
                    ->visibleFrom('md'),
            ])
            ->paginated([5]) // Paginación pequeña para no saturar el Dashboard visualmente
            ->striped(); // Diseño de filas alternadas
    }

    private static function formatBatchStock(ProductBatch $record): string
    {
        $product = $record->product;

        if (! $product) {
            return (string) $record->current_quantity;
        }

        $stock = (float) $record->current_quantity;

        if ($product->is_fractionable && $product->units_per_box > 0) {
            $cajas = floor(abs($stock));
            $fraccion = abs($stock) - $cajas;
            $unidades = round($fraccion * $product->units_per_box);

            $texto = [];

            if ($cajas > 0) {
                $texto[] = "{$cajas} caj";
            }

            if ($unidades > 0) {
                $texto[] = "{$unidades} und";
            }

            return empty($texto) ? '0 und' : implode(' y ', $texto);
        }

        if ($product->is_weighable) {
            $codigoUnidad = $product->unidadSunat?->codigo ?? '';

            $sufijo = match ($codigoUnidad) {
                'KGM' => 'kg',
                'LTR' => 'lt',
                'GLL' => 'gal',
                default => 'und',
            };

            return number_format($stock, 2) . " {$sufijo}";
        }

        return number_format($stock, 0) . ' und';
    }
}
