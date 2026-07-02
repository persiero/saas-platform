<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers;
use Percy\Core\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Services\Tenants\TenantPlanService;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?string $modelLabel = 'Categoría';
    protected static ?string $pluralModelLabel = 'Categorías';
    protected static ?int $navigationSort = 2;

    private static function userCanManageCategories(): bool
    {
        /** @var \Percy\Core\Models\User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->tenant) {
            return false;
        }

        if (! $user->isAdmin()) {
            return false;
        }

        return app(TenantPlanService::class)->has('has_product_categories', $user->tenant);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        if (! $user || $user->tenant_id === null) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->where('tenant_id', $user->tenant_id)
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    // 🌟 MAGIA SAAS: Ocultar este menú a los Mozos (Vendedores)
    public static function canViewAny(): bool
    {
        return self::userCanManageCategories();
    }

    public static function canCreate(): bool
    {
        return self::userCanManageCategories();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::userCanManageCategories();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::userCanManageCategories();
    }

    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::userCanManageCategories();
    }

    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    // 5. Restricción general para Bulk Actions (Aplica para eliminar/restaurar masivamente)
    public static function canDeleteAny(): bool
    {
        return self::userCanManageCategories();
    }

    public static function canRestoreAny(): bool
    {
        return self::userCanManageCategories();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre de Categoría')
                    ->required()
                    ->maxLength(150)
                    ->placeholder('Ej: Bebidas, Comidas, Postres')
                    ->prefixIcon('heroicon-o-tag')
                    ->columnSpan(2),

                Forms\Components\Textarea::make('description')
                    ->label('Descripción')
                    ->maxLength(255)
                    ->rows(3)
                    ->placeholder('Descripción opcional de la categoría')
                    ->columnSpan(2),

                Forms\Components\Toggle::make('active')
                    ->label('¿Categoría Activa?')
                    ->helperText('Las categorías inactivas no aparecerán en los formularios')
                    ->default(true)
                    ->inline(false)
                    ->columnSpan(2),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Categoría')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-tag')
                    ->description(fn(Category $record): ?string => $record->description),

                Tables\Columns\ToggleColumn::make('active')
                    ->label('Activo')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->since()
                    ->icon('heroicon-o-calendar')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Estado')
                    ->placeholder('Todas las categorías')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas')
                    ->native(false),

                TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Ver detalles')
                        ->icon('heroicon-o-eye')
                        ->color('info'),

                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil')
                        ->color('warning'),

                    Tables\Actions\DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Categoría')
                        ->modalDescription('¿Estás seguro de que deseas eliminar esta categoría? Esta acción no se puede deshacer.'),

                    Tables\Actions\RestoreAction::make()
                        ->label('Restaurar')
                        ->icon('heroicon-o-arrow-uturn-left') // Icono de "Deshacer"
                        ->color('success') // Color verde positivo
                        ->requiresConfirmation()
                        ->modalHeading('Restaurar Categoría')
                        ->modalDescription('¿Deseas rescatar esta categoría de la papelera? Volverá a estar visible y activo en el sistema.'),
                ])
                    ->label('Acciones')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->button()
                    ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Categorías')
                        ->modalDescription('¿Estás seguro de que deseas eliminar las categorías seleccionados?'),
                    Tables\Actions\RestoreBulkAction::make(), // 🌟 Restaurar varios a la vez
                ]),
            ])
            ->emptyStateHeading('Sin categorías registradas')
            ->emptyStateDescription('Comienza creando tu primera categoría para organizar tus productos')
            ->emptyStateIcon('heroicon-o-tag');
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
            'index' => Pages\ListCategories::route('/'),
            //'create' => Pages\CreateCategory::route('/create'),
            //'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
