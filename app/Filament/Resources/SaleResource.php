<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Filament\Resources\SaleResource\RelationManagers;
use App\Services\SunatService;
use App\Filament\Resources\SaleResource\Schemas\SaleInfolist;
use App\Filament\Resources\SaleResource\Schemas\SaleTable;
use App\Filament\Resources\SaleResource\Schemas\SaleForm;
use Percy\Core\Models\Sale;
use Filament\Forms\Form;
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

    public static function table(Table $table): Table
    {
        return SaleTable::configure($table);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return SaleInfolist::configure($infolist);
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
