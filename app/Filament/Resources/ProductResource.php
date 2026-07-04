<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use Percy\Core\Models\Product;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;
use Percy\Core\Services\Tenants\TenantFeatureService;
use App\Filament\Resources\ProductResource\Tables\ProductTable;
use App\Filament\Resources\ProductResource\Schemas\ProductForm;
use Percy\Core\Services\Tenants\TenantPlanService;
use Filament\Infolists\Infolist;
use App\Filament\Resources\ProductResource\Schemas\ProductInfolist;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?string $modelLabel = 'Producto';
    protected static ?string $pluralModelLabel = 'Productos';
    protected static ?int $navigationSort = 3;

    public static function tenantHasAvailableProductSlots(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->tenant) {
            return false;
        }

        $limit = app(TenantPlanService::class)->limit('max_products', $user->tenant);

        if ($limit === null) {
            return true;
        }

        $products = Product::query()
            ->where('tenant_id', $user->tenant_id)
            ->count();

        return $products < (int) $limit;
    }

    public static function productLimitMessage(): string
    {
        $user = Auth::user();

        if (! $user || ! $user->tenant) {
            return 'No se pudo validar el límite de productos del plan.';
        }

        $limit = app(TenantPlanService::class)->limit('max_products', $user->tenant);

        $products = Product::query()
            ->where('tenant_id', $user->tenant_id)
            ->count();

        return "Tu plan actual permite hasta {$limit} productos o servicios. Actualmente tienes {$products}.";
    }

    // Filtro global para asegurar que solo se vean productos del tenant actual
    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()
            ->with(['category', 'unidadSunat'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        if (!$user?->isSuperAdmin()) {
            $query->where('tenant_id', $user?->tenant_id);
        }

        return $query;
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->canViewProducts() ?? false;
    }

    public static function canCreate(): bool
    {
        return (Auth::user()?->canCreateProducts() ?? false)
            && self::tenantHasAvailableProductSlots();
    }

    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->canEditProducts() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->canDeleteProducts() ?? false;
    }

    public static function canRestore(Model $record): bool
    {
        return Auth::user()?->canRestoreProducts() ?? false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return Auth::user()?->canDeleteProducts() ?? false;
    }

    public static function canRestoreAny(): bool
    {
        return Auth::user()?->canRestoreProducts() ?? false;
    }

    private static function tenantFeatures(): array
    {
        return app(TenantFeatureService::class)->features();
    }

    public static function form(Form $form): Form
    {
        return ProductForm::configure($form);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return ProductInfolist::configure($infolist);
    }

    public static function table(Table $table): Table
    {
        return ProductTable::configure($table);
    }

    public static function getRelations(): array
    {
        $relations = [];

        // MAGIA DEL SAAS: Encendemos el módulo leyendo el JSON de características
        $features = self::tenantFeatures();

        $hasLots = $features['has_lots'] ?? false;
        $hasExpiry = $features['has_expiry_dates'] ?? false;

        // 🌟 EL CAMBIO CLAVE: Si el negocio usa lotes (Farmacia) O usa fechas de vencimiento (Minimarket)
        if ($hasLots || $hasExpiry) {
            $relations[] = RelationManagers\BatchesRelationManager::class;
        }

        return $relations;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
