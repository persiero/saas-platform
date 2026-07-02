<?php

namespace App\Filament\Resources\UserResource\Schemas;

use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('tenant_id')
                    ->relationship(
                        'tenant',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('is_active', true)
                    )
                    ->label('Negocio / Sucursal')
                    ->default(fn (): ?int => Auth::user()?->tenant_id)
                    ->disabled(fn (): bool => Auth::user()?->tenant_id !== null)
                    ->dehydrated()
                    ->searchable()
                    ->preload()
                    ->helperText(fn (): string =>
                        Auth::user()?->isSuperAdmin()
                            ? 'Selecciona la empresa del cliente. Déjalo vacío solo para crear otro usuario global del SaaS.'
                            : 'El usuario será asignado automáticamente a tu negocio.'
                    )
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
                    ->dehydrateStateUsing(fn (?string $state): ?string =>
                        filled($state) ? strtolower(trim($state)) : null
                    )
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
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                    ->helperText('Déjalo vacío si no deseas cambiar la contraseña.')
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Usuario Activo')
                    ->default(true)
                    ->helperText('Apágalo para quitarle el acceso al sistema sin borrar su historial.')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
