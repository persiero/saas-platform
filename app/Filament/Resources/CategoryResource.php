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

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Categorías';
    protected static ?string $modelLabel = 'Categoría';
    protected static ?string $pluralModelLabel = 'Categorías';
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?int $navigationSort = 21;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id);
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
                    ->description(fn (Category $record): ?string => $record->description),

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
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil'),
                    Tables\Actions\DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation(),
                ])
                ->label('Acciones')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button()
                ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
