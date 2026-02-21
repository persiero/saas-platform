<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SerieResource\Pages;
use App\Filament\Resources\SerieResource\RelationManagers;
use Percy\Core\Models\Serie;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SerieResource extends Resource
{
    protected static ?string $model = Serie::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationLabel = 'Series y Comprobantes';

    // Lo ponemos en un grupo de configuración para mantener ordenado el menú
    protected static ?string $navigationGroup = 'Configuración';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Configuración de la Serie')->schema([
                    Forms\Components\Select::make('document_type')
                        ->label('Tipo de Comprobante')
                        ->options([
                            '01' => 'Factura Electrónica (01)',
                            '03' => 'Boleta Electrónica (03)',
                            '07' => 'Nota de Crédito (07)',
                            '08' => 'Nota de Débito (08)',
                        ])
                        ->required(),

                    Forms\Components\TextInput::make('serie')
                        ->label('Serie (Ej: F001, B001, FC01)')
                        ->required()
                        ->maxLength(4)
                        // Pequeño truco visual para que el usuario escriba en mayúsculas
                        ->extraInputAttributes(['style' => 'text-transform: uppercase']), 

                    Forms\Components\TextInput::make('correlative')
                        ->label('Último Correlativo Emitido')
                        ->helperText('Si inicias desde cero, pon 0. Si vienes de otro sistema y tu última boleta fue la 150, pon 150.')
                        ->numeric()
                        ->required()
                        ->default(0),

                    Forms\Components\Toggle::make('active')
                        ->label('Serie Activa')
                        ->default(true),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Tipo')
                    // Formateamos el número para que sea legible en la tabla
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        '01' => 'Factura (01)',
                        '03' => 'Boleta (03)',
                        '07' => 'Nota de Crédito (07)',
                        '08' => 'Nota de Débito (08)',
                        default => $state,
                    })
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('serie')
                    ->label('Serie')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('correlative')
                    ->label('Correlativo Actual')
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                //
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
            'index' => Pages\ListSeries::route('/'),
            'create' => Pages\CreateSerie::route('/create'),
            'edit' => Pages\EditSerie::route('/{record}/edit'),
        ];
    }
}
