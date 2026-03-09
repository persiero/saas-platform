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
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SerieResource extends Resource
{
    protected static ?string $model = Serie::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationLabel = 'Series Comprobantes';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 62;

    public static function form(Form $form): Form
    {
        return $form
        ->schema([
            Forms\Components\Select::make('document_type')
                ->label('Tipo de comprobante')
                ->options([
                    '01' => 'Factura Electrónica',
                    '03' => 'Boleta Electrónica',
                    '07' => 'Nota de Crédito',
                    '08' => 'Nota de Débito',
                ])
                ->required()
                ->native(false)
                ->live() // Hace que reaccione al instante
                ->columnSpan(2),

            Forms\Components\TextInput::make('serie')
                ->label('Serie')
                ->placeholder('Ej: F001')
                ->required()
                ->length(4)
                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                // Regla de validación mágica:
                ->rules([
                    fn (\Filament\Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                        $type = $get('document_type');
                        $value = strtoupper($value);

                        if ($type === '01' && !str_starts_with($value, 'F')) {
                            $fail('La serie para Facturas debe empezar con F.');
                        }
                        if ($type === '03' && !str_starts_with($value, 'B')) {
                            $fail('La serie para Boletas debe empezar con B.');
                        }
                        if (in_array($type, ['07', '08']) && !in_array(substr($value, 0, 1), ['F', 'B'])) {
                            $fail('Las notas deben empezar con F (si es de factura) o B (si es de boleta).');
                        }
                    },
                ])
                ->columnSpan(1),

            Forms\Components\TextInput::make('correlative')
                ->label('Último número emitido')
                ->numeric()
                ->required()
                ->default(0)
                ->helperText('Pon 0 si es una serie nueva.')
                ->columnSpan(1),

            Forms\Components\Toggle::make('active')
                ->label('Serie activa')
                ->default(true)
                ->columnSpan(2),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('document_type')
                    ->label('Tipo de comprobante')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        '01' => 'Factura',
                        '03' => 'Boleta',
                        '07' => 'Nota Crédito',
                        '08' => 'Nota Débito',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        '01' => 'success',
                        '03' => 'info',
                        '07' => 'warning',
                        '08' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('serie')
                    ->label('Serie')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('correlative')
                    ->label('Último correlativo')
                    ->sortable(),

                Tables\Columns\TextColumn::make('next_number')
                    ->label('Siguiente comprobante')
                    ->state(fn ($record) => $record->serie . '-' . str_pad($record->correlative + 1, 8, '0', STR_PAD_LEFT)),

                Tables\Columns\ToggleColumn::make('active')
                    ->label('Activa'),

            ])
            ->filters([
                //
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
            //'create' => Pages\CreateSerie::route('/create'),
            //'edit' => Pages\EditSerie::route('/{record}/edit'),
        ];
    }
}
