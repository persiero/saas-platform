<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Filament\Resources\SaleResource\RelationManagers;
use App\Services\SunatService;
use App\Filament\Resources\SaleResource\Schemas\SaleInfolist;
use App\Filament\Resources\SaleResource\Schemas\SaleTable;
use App\Filament\Resources\SaleResource\Schemas\SaleForm;
use Percy\Core\Models\Sale;
use Percy\Core\Services\Sales\CorrelativeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Tables\Actions\Action;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $modelLabel = 'Venta';
    protected static ?string $pluralModelLabel = 'Ventas';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // 1. Filtro por local (Tenant)
            ->where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id)

            // 2. Ocultamos los borradores de mesas vacías
            ->where(function ($query) {
                $query->where('total', '>', 0)->orWhere('status', '!=', 'pending');
            })

            // 🌟 3. LA SOLUCIÓN DEFINITIVA:
            // Agregamos 'user' y 'cashRegister.user' para que la columna apilada
            // no tenga que buscar los nombres uno por uno.
            ->with([
                'user',
                'cashRegister.user',
                'items.product',
                'items.product.unidadSunat'
            ]);
    }

    // 1. Candado Inteligente: Nadie edita una venta ya hecha... EXCEPTO los pedidos web entrantes.
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // 🌟 PERMITIR EDICIÓN: Solo si es pedido web y está pendiente de validación/cobro
        if ($record->channel === 'ecommerce' && $record->status === 'pending_payment') {
            return true;
        }

        // 🔒 BLOQUEAR EDICIÓN: Para todas las ventas presenciales o web ya completadas
        return false;
    }

    // 2.Nadie borra una venta ya hecha (se anulan con Nota de Crédito, no se borran de la base de datos).
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    // 3. CANDADO: Nadie hace borrado masivo.
    public static function canDeleteAny(): bool
    {
        return false;
    }

    // 4. CANDADO: Nadie destruye registros de la BD.
    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    // 5. CANDADO: Nadie destruye registros masivamente.
    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    // Todos pueden crear ventas (Cajeros y Admins).
    public static function canCreate(): bool
    {
        return true;
    }

    // Asegurarnos de que toda nueva venta guarde el ID del cajero y el tenant
    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        $data['tenant_id'] = Auth::user()->tenant_id;

        return $data;
    }

    public static function form(Form $form): Form
    {
        return SaleForm::configure($form);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return SaleInfolist::configure($infolist);
    }

    public static function table(Table $table): Table
    {
        return SaleTable::configure($table);
    }

    // =========================================================================
    // MÉTODOS DE CÁLCULO MATEMÁTICO (Facturación SUNAT)
    // =========================================================================

    public static function updateRow(Get $get, Set $set): void
    {
        $quantity = (float) ($get('quantity') ?? 1);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $afectacionId = $get('afectacion_igv_id') ?? 1;

        // 🌟 1. Obtenemos el IGV dinámico del Tenant
        $tenantIgv = \Illuminate\Support\Facades\Auth::user()->tenant->igv_percentage ?? 18;

        $afectacion = \Percy\Core\Models\AfectacionIgv::find($afectacionId);

        // 🌟 2. Si es gravado, usamos el IGV del negocio (18% o 10.5%)
        $porcentaje = ($afectacion && $afectacion->gravado) ? ($tenantIgv / 100) : 0;

        $rowTotal = $quantity * $unitPrice;
        $unitValue = $unitPrice / (1 + $porcentaje);
        $igvAmount = ($unitPrice - $unitValue) * $quantity;

        // Guarda en los campos de la fila (incluso los ocultos)
        $set('unit_value', round($unitValue, 2));
        $set('igv_amount', round($igvAmount, 2));
        $set('total', round($rowTotal, 2));
    }

    /**
     * Recorre todas las filas y suma los totales globales para el panel derecho
     */
    public static function updateTotals(Get $get, Set $set): void
    {
        // TRUCO AVANZADO: Detecta si estamos dentro del Repetidor o fuera de él.
        $items = $get('items');
        if ($items === null) {
            // Si es null, significa que estamos dentro de una fila. ¡Subimos un nivel!
            $items = $get('../../items') ?? [];
            $prefix = '../../'; // Usamos este prefijo para apuntar al panel derecho
        } else {
            // Si no es null, estamos en la raíz del formulario
            $prefix = '';
        }

        $op_gravadas = 0;
        $op_exoneradas = 0;
        $op_inafectas = 0;
        $igv = 0;
        $totalGeneral = 0;

        // 🌟 1. Obtenemos el IGV dinámico UNA SOLA VEZ antes del bucle
        // (Hacerlo afuera hace que el sistema sea mucho más rápido)
        $tenantIgv = \Illuminate\Support\Facades\Auth::user()->tenant->igv_percentage ?? 18;

        foreach ($items as $item) {
            // Recalculamos al vuelo para tener la matemática 100% fresca
            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $afecId = $item['afectacion_igv_id'] ?? 1;

            $afectacion = \Percy\Core\Models\AfectacionIgv::find($afecId);

            // 🌟 2. Aplicamos el IGV dinámico
            $porcentaje = ($afectacion && $afectacion->gravado) ? ($tenantIgv / 100) : 0;

            $rowTotal = $qty * $price;

            if ($afectacion && $afectacion->gravado) {
                $base = $rowTotal / (1 + $porcentaje);
                $op_gravadas += $base;
                $igv += ($rowTotal - $base);
            } elseif ($afectacion && str_starts_with($afectacion->codigo, '2')) {
                $op_exoneradas += $rowTotal;
            } elseif ($afectacion && str_starts_with($afectacion->codigo, '3')) {
                $op_inafectas += $rowTotal;
            }

            $totalGeneral += $rowTotal;
        }

        // Actualizamos el panel derecho inyectando los datos con su prefijo
        $set($prefix . 'op_gravadas', round($op_gravadas, 2));
        $set($prefix . 'op_exoneradas', round($op_exoneradas, 2));
        $set($prefix . 'op_inafectas', round($op_inafectas, 2));
        $set($prefix . 'igv', round($igv, 2));
        $set($prefix . 'total', round($totalGeneral, 2));
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'view' => Pages\ViewSale::route('/{record}'), // NUEVA RUTA DE LECTURA
            //'edit' => Pages\EditSale::route('/{record}/edit'),
        ];
    }
}
