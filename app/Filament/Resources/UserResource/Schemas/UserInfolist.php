<?php

namespace App\Filament\Resources\UserResource\Schemas;

use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;

class UserInfolist
{
    public static function configure(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Detalle del Usuario')
                    ->icon('heroicon-o-user')
                    ->description('Información de acceso, empresa asignada y roles del usuario.')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Usuario')
                            ->weight('black')
                            ->size(TextEntry\TextEntrySize::Large)
                            ->icon('heroicon-o-user')
                            ->columnSpanFull(),

                        TextEntry::make('email')
                            ->label('Correo electrónico')
                            ->icon('heroicon-o-envelope')
                            ->copyable()
                            ->copyMessage('Correo copiado')
                            ->placeholder('Sin correo'),

                        TextEntry::make('tenant.name')
                            ->label('Empresa')
                            ->badge()
                            ->color(fn($state): string => $state ? 'success' : 'danger')
                            ->placeholder('Usuario global del SaaS')
                            ->default('Usuario global del SaaS'),

                        TextEntry::make('roles_summary')
                            ->label('Roles asignados')
                            ->state(fn(User $record): string => $record->roles->pluck('name')->join(', ') ?: 'Sin rol')
                            ->badge()
                            ->color('info'),

                        IconEntry::make('is_active')
                            ->label('Estado')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        TextEntry::make('created_at')
                            ->label('Fecha de creación')
                            ->dateTime('d/m/Y h:i A')
                            ->icon('heroicon-o-calendar'),

                        TextEntry::make('updated_at')
                            ->label('Última actualización')
                            ->dateTime('d/m/Y h:i A')
                            ->icon('heroicon-o-clock'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),
            ]);
    }
}
