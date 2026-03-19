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
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
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

    public static function canViewAny(): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();

        // Bloquea a los cajeros, permite el paso a los Admins (tanto al Súper Admin como al Dueño local)
        return $user->isAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // EL SELECTOR DE NEGOCIO INTELIGENTE
                Forms\Components\Select::make('tenant_id')
                    ->relationship('tenant', 'name')
                    ->label('Negocio / Sucursal')
                    ->default(fn () => Auth::user()->tenant_id) // Por defecto toma el ID de quien lo crea
                    ->disabled(fn () => Auth::user()->tenant_id !== null) // Bloquea el campo si NO eres el Súper Admin
                    ->dehydrated() // OBLIGATORIO: Le dice a Filament que guarde el dato en la BD aunque el campo esté bloqueado
                    ->searchable()
                    ->preload()
                    ->helperText(fn () => Auth::user()->tenant_id === null
                        ? 'Selecciona la empresa del cliente. (Déjalo vacío para crear otro Súper Administrador).'
                        : 'El usuario será asignado automáticamente a tu negocio.')
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('name')
                    ->label('Nombre completo')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Ej: Juan Pérez')
                    ->columnSpan(1),

                Forms\Components\TextInput::make('email')
                    ->label('Correo electrónico')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->placeholder('usuario@empresa.com')
                    ->columnSpan(1),

                Forms\Components\Select::make('roles')
                    ->relationship('roles', 'name')
                    ->label('Roles asignados')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText('Los roles determinan qué módulos puede usar el usuario.')
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->dehydrateStateUsing(fn (string $state) => \Illuminate\Support\Facades\Hash::make($state)) // ENCRIPTACIÓN MÁGICA
                    ->helperText('Déjalo vacío si no deseas cambiar la contraseña.')
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                ->label('Usuario Activo')
                ->default(true)
                ->helperText('Apágalo para quitarle el acceso al sistema sin borrar su historial.'),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Empresa')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        null => 'danger', // Rojo para los Súper Admins
                        default => 'success', // Verde para los usuarios de negocios
                    })
                    ->default('SÚPER ADMIN') // Texto si el tenant_id es null
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color('info')
                    ->separator(','),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de creación')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                ->label('Activo')
                ->sortable(),

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
            //'create' => Pages\CreateUser::route('/create'),
            //'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
