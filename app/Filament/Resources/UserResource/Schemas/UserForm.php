<?php

namespace App\Filament\Resources\UserResource\Schemas;

use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;

class UserForm
{
    public static function configure(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Usuario')
                    ->description('Información básica de acceso al sistema.')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\Select::make('tenant_id')
                            ->relationship(
                                'tenant',
                                'name',
                                modifyQueryUsing: fn(Builder $query): Builder => $query->where('is_active', true)
                            )
                            ->label('Negocio / Sucursal')
                            ->default(fn(): ?int => Auth::user()?->tenant_id)
                            ->disabled(fn(): bool => Auth::user()?->tenant_id !== null)
                            ->dehydrated()
                            ->searchable()
                            ->preload()
                            ->hintIcon(
                                'heroicon-m-question-mark-circle',
                                tooltip: Auth::user()?->isSuperAdmin()
                                    ? 'Selecciona la empresa del cliente. Déjalo vacío solo para crear otro usuario global del SaaS.'
                                    : 'El usuario será asignado automáticamente a tu negocio.'
                            )
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre completo')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Juan Pérez'),

                        Forms\Components\TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('usuario@empresa.com')
                            ->dehydrateStateUsing(
                                fn(?string $state): ?string =>
                                filled($state) ? strtolower(trim($state)) : null
                            ),

                        Forms\Components\TextInput::make('password')
                            ->label(
                                fn(string $operation): string =>
                                $operation === 'create' ? 'Contraseña' : 'Nueva contraseña'
                            )
                            ->password()
                            ->revealable()
                            ->required(fn(string $operation): bool => $operation === 'create')
                            ->dehydrated(fn(?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn(string $state): string => Hash::make($state))
                            ->helperText(
                                fn(string $operation): string =>
                                $operation === 'create'
                                    ? 'Define una contraseña inicial para este usuario.'
                                    : 'Déjalo vacío si no deseas cambiar la contraseña.'
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),

                Forms\Components\Section::make('Acceso y Permisos')
                    ->description('Define el rol y el estado del usuario.')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
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

                        Forms\Components\Toggle::make('is_active')
                            ->label('Usuario Activo')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Desactívalo para quitarle el acceso al sistema sin borrar su historial.')
                            ->disabled(fn(?Model $record): bool => $record?->id === Auth::id())
                            ->dehydrated(fn(?Model $record): bool => $record?->id !== Auth::id())
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),
            ]);
    }
}
