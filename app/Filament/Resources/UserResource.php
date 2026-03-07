<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Usuarios';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 63;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ESTE CAMPO OCULTO ASIGNA EL NEGOCIO AUTOMÁTICAMENTE
                Forms\Components\Hidden::make('tenant_id')
                    ->default(fn () => Auth::user()->tenant_id),

                Forms\Components\TextInput::make('name')
                    ->label('Nombre Completo')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->label('Correo Electrónico')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Forms\Components\TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->revealable(),

                Forms\Components\Select::make('roles')
                    ->relationship('roles', 'name')
                    ->label('Roles Asignados')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge() // Muestra los roles como etiquetas bonitas
                    ->color('info'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado el')
                    ->dateTime('d/m/Y')
                    ->sortable(),
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

    // EL NUEVO ESCUDO: Solo ves los usuarios de tu propio negocio
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // <-- Cambiado en esta línea:
        if (Auth::check() && Auth::user()->tenant_id) {
            $query->where('tenant_id', Auth::user()->tenant_id);
        }

        return $query;
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
