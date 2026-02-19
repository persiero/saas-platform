<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use Percy\Core\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre / Razón Social')
                    ->required()
                    ->maxLength(150)
                    ->columnSpan(2), // Ocupa el doble de espacio
                    
                Forms\Components\Select::make('document_type')
                    ->label('Tipo de Documento')
                    ->options([
                        'DNI' => 'DNI',
                        'RUC' => 'RUC',
                        'CE' => 'Carné de Extranjería',
                        'PASAPORTE' => 'Pasaporte',
                        'OTROS' => 'Otros',
                    ]),
                    
                Forms\Components\TextInput::make('document_number')
                    ->label('Número de Documento')
                    ->maxLength(20),
                    
                Forms\Components\TextInput::make('phone')
                    ->label('Teléfono')
                    ->tel() // Teclado numérico en móviles
                    ->maxLength(30),
                    
                Forms\Components\TextInput::make('email')
                    ->label('Correo Electrónico')
                    ->email() // Valida que tenga un @
                    ->maxLength(150),
                    
                Forms\Components\TextInput::make('address')
                    ->label('Dirección')
                    ->maxLength(255)
                    ->columnSpanFull(), // Hace que la dirección ocupe todo el ancho inferior
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre / Razón Social')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Tipo Doc.')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('document_number')
                    ->label('N° Documento')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), // Se puede ocultar/mostrar con un botón
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado el')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(), // Botón para eliminar
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
