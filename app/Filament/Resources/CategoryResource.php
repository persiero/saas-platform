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
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;

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
                Forms\Components\Section::make('Información de la Categoría')
                    ->description('Organiza tus productos o servicios por grupos.')
                    ->icon('heroicon-o-tag')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de Categoría')
                            ->required()
                            ->maxLength(150)
                            ->placeholder('Ej: Bebidas, Comidas, Postres')
                            ->prefixIcon('heroicon-o-tag')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->maxLength(255)
                            ->rows(2)
                            ->placeholder('Descripción opcional de la categoría')
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('active')
                            ->label('Categoría activa')
                            ->default(true)
                            ->inline(false)
                            ->hintIcon(
                                'heroicon-m-question-mark-circle',
                                tooltip: 'Las categorías inactivas no aparecerán como opción principal en productos.'
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Detalle de la Categoría')
                    ->icon('heroicon-o-tag')
                    ->description('Información registrada para organizar productos y servicios.')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Categoría')
                            ->weight('black')
                            ->icon('heroicon-o-tag')
                            ->columnSpanFull(),

                        TextEntry::make('active')
                            ->label('Estado')
                            ->formatStateUsing(fn(bool $state): string => $state ? 'Activa' : 'Inactiva')
                            ->badge()
                            ->color(fn(bool $state): string => $state ? 'success' : 'danger'),

                        TextEntry::make('created_at')
                            ->label('Creada')
                            ->dateTime('d/m/Y h:i A')
                            ->icon('heroicon-o-calendar'),

                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('Sin descripción')
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->recordUrl(null)
            ->recordAction('view')
            ->columns([
                Tables\Columns\TextColumn::make('mobile_summary')
                    ->label('Categoría')
                    ->state(fn(Category $record): string => $record->name)
                    ->description(function (Category $record): string {
                        $estado = $record->active ? 'Activa' : 'Inactiva';
                        $descripcion = $record->description ?: 'Sin descripción';

                        return "{$estado} · {$descripcion}";
                    })
                    ->icon('heroicon-o-tag')
                    ->weight('black')
                    ->wrap()
                    ->searchable(['name', 'description'])
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Categoría')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-tag')
                    ->description(fn(Category $record): ?string => $record->description)
                    ->visibleFrom('md'),

                Tables\Columns\ToggleColumn::make('active')
                    ->label('Activo')
                    ->sortable()
                    ->visibleFrom('md'),

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
                    ->tooltip('Acciones')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
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
