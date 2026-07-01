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
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $modelLabel = 'Usuario';
    protected static ?string $pluralModelLabel = 'Usuarios';
    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = Auth::user();

        if (! $user?->isAdmin()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return (int) $record->tenant_id === (int) $user->tenant_id;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // EL SELECTOR DE NEGOCIO INTELIGENTE
                Forms\Components\Select::make('tenant_id')
                    ->relationship(
                        'tenant',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)
                    )
                    ->label('Negocio / Sucursal')
                    ->default(fn () => Auth::user()?->tenant_id)
                    ->disabled(fn () => Auth::user()?->tenant_id !== null)
                    ->dehydrated()
                    ->searchable()
                    ->preload()
                    ->helperText(fn () => Auth::user()?->isSuperAdmin()
                        ? 'Selecciona la empresa del cliente. Déjalo vacío solo para crear otro usuario global del SaaS.'
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
                    ->relationship(
                        'roles',
                        'name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            if (Auth::user()?->isSuperAdmin()) {
                                return $query;
                            }

                            return $query->where('name', '!=', 'Super Admin');
                        }
                    )
                    ->label('Roles asignados')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->required()
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

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn ($record): string =>
                        $record->is_active
                            ? 'Usuario activo'
                            : 'Usuario desactivado'
                    ),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil')
                        ->color('warning'),

                    Tables\Actions\Action::make('deactivateUser')
                        ->label('Desactivar usuario')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->visible(fn ($record): bool =>
                            $record->is_active &&
                            (Auth::user()?->isAdmin() ?? false) &&
                            Auth::id() !== $record->id
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Desactivar usuario')
                        ->modalDescription(fn ($record): string =>
                            "El usuario {$record->name} ya no podrá ingresar al sistema, pero su historial se conservará."
                        )
                        ->modalSubmitActionLabel('Sí, desactivar')
                        ->modalCancelActionLabel('Cancelar')
                        ->action(function ($record): void {
                            $record->update([
                                'is_active' => false,
                            ]);

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Usuario desactivado')
                                ->body('El acceso del usuario fue desactivado correctamente.')
                                ->send();
                        }),

                    Tables\Actions\Action::make('reactivateUser')
                        ->label('Reactivar usuario')
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->visible(fn ($record): bool =>
                            ! $record->is_active &&
                            (Auth::user()?->isAdmin() ?? false)
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Reactivar usuario')
                        ->modalDescription(fn ($record): string =>
                            "El usuario {$record->name} podrá ingresar nuevamente al sistema."
                        )
                        ->modalSubmitActionLabel('Sí, reactivar')
                        ->modalCancelActionLabel('Cancelar')
                        ->action(function ($record): void {
                            $record->update([
                                'is_active' => true,
                            ]);

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Usuario reactivado')
                                ->body('El acceso del usuario fue reactivado correctamente.')
                                ->send();
                        }),
                ])
                    ->label('Acciones')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->button()
                    ->color('gray'),
            ])
            ->bulkActions([]);
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
