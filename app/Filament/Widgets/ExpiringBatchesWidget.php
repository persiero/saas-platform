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
    protected int | string | array $columnSpan = 'full';

    // Le damos prioridad para que aparezca arriba, justo debajo de los botones
    protected static ?int $sort = 2;

    // ¡EL ESCUDO DEL SAAS! Solo las farmacias pueden ver este widget
    public static function canView(): bool
    {
        $features = \Illuminate\Support\Facades\Auth::user()->tenant->businessSector->features ?? [];
        // Mostramos el widget SOLO a los negocios que manejan fechas de vencimiento
        return $features['has_expiry_dates'] ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Buscamos los lotes del negocio actual que tengan stock y venzan en los próximos 90 días (o ya estén vencidos)
                ProductBatch::query()
                    ->with('product') // Traemos el nombre del producto para no sobrecargar la base de datos
                    ->where('tenant_id', Auth::user()->tenant_id)
                    ->where('current_quantity', '>', 0) // No alertamos si ya se acabó el stock
                    ->where('expiration_date', '<=', now()->addDays(90))
                    ->orderBy('expiration_date', 'asc') // Los más urgentes arriba
            )
            ->heading('🚨 Alerta de Vencimientos (Próximos 90 días)')
            ->description('Lotes de medicamentos que requieren rotación urgente o retiro de los estantes.')

            // --- NUEVO: TRADUCCIÓN DEL ESTADO VACÍO ---
            ->emptyStateHeading('¡Todo en orden!')
            ->emptyStateDescription('No hay medicamentos próximos a vencer en los siguientes 90 días.')
            ->emptyStateIcon('heroicon-o-check-badge')
            // ------------------------------------------

            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Medicamento')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('batch_number')
                    ->label('Lote')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('expiration_date')
                    ->label('Fecha de Vencimiento')
                    ->date('d/m/Y')
                    ->badge()
                    ->color(function ($state): string {
                        if (Carbon::parse($state)->isPast()) return 'danger'; // Rojo si ya venció
                        return 'warning'; // Amarillo/Naranja si está por vencer
                    }),

                Tables\Columns\TextColumn::make('current_quantity')
                    ->label('Stock Atrapado')
                    ->numeric()
                    // Si quedan más de 10 cajas por vencer, es crítico (rojo), si son pocas, gris.
                    ->color(fn ($state) => $state > 10 ? 'danger' : 'gray'),
            ])
            ->paginated([5]) // Paginación pequeña para no saturar el Dashboard visualmente
            ->striped(); // Diseño de filas alternadas
    }
}
