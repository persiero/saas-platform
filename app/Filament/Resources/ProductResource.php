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

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?string $modelLabel = 'Producto';
    protected static ?string $pluralModelLabel = 'Productos';
    protected static ?int $navigationSort = 3;

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
        return Auth::user()?->canCreateProducts() ?? false;
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
