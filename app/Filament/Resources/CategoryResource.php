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

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre de Categoría')
                    ->required()
                    ->maxLength(150),

                Forms\Components\TextInput::make('description')
                    ->label('Descripción')
                    ->maxLength(255),

                Forms\Components\Toggle::make('active')
                    ->label('¿Activa?')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable() // Agrega una barra de búsqueda para este campo
                    ->sortable(),  // Permite ordenar alfabéticamente
                    
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(50) // Si la descripción es muy larga, la recorta
                    ->searchable(),
                    
                Tables\Columns\IconColumn::make('active')
                    ->label('Activa')
                    ->boolean(), // Muestra un check verde o una X roja
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                // Aquí luego podemos agregar filtros (ej. ver solo las inactivas)
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
