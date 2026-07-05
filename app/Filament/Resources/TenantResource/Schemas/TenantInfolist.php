<?php

namespace App\Filament\Resources\TenantResource\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Percy\Core\Models\Tenant;

class TenantInfolist
{
    public static function configure(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Cliente SaaS')
                    ->icon('heroicon-o-building-storefront')
                    ->description('Información general del negocio, plan contratado y acceso al sistema.')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nombre comercial')
                            ->weight('black')
                            ->icon('heroicon-o-building-storefront')
                            ->columnSpanFull(),

                        TextEntry::make('business_name')
                            ->label('Razón social')
                            ->placeholder('Sin razón social'),

                        TextEntry::make('ruc')
                            ->label('RUC')
                            ->copyable()
                            ->copyMessage('RUC copiado')
                            ->placeholder('Sin RUC'),

                        TextEntry::make('businessSector.name')
                            ->label('Giro del negocio')
                            ->badge()
                            ->color('info')
                            ->placeholder('Sin giro'),

                        TextEntry::make('plan.name')
                            ->label('Plan contratado')
                            ->badge()
                            ->color(fn(?string $state): string => match ($state) {
                                'Básico' => 'gray',
                                'Estándar' => 'info',
                                'Premium' => 'success',
                                default => 'warning',
                            })
                            ->placeholder('Sin plan'),

                        TextEntry::make('domain')
                            ->label('Subdominio')
                            ->formatStateUsing(fn(?string $state): string => $state ? "{$state}.virtualperu.online" : 'Sin dominio')
                            ->copyable()
                            ->copyMessage('Dominio copiado'),

                        IconEntry::make('is_active')
                            ->label('Acceso')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        IconEntry::make('sunat_certificate')
                            ->label('Certificado SUNAT')
                            ->boolean()
                            ->state(fn(Tenant $record): bool => filled($record->sunat_certificate))
                            ->trueIcon('heroicon-o-check-badge')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        TextEntry::make('created_at')
                            ->label('Creado')
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

                Section::make('Contacto y facturación')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        TextEntry::make('address')
                            ->label('Dirección fiscal')
                            ->placeholder('Sin dirección')
                            ->columnSpanFull(),

                        TextEntry::make('phone')
                            ->label('Teléfono')
                            ->icon('heroicon-o-phone')
                            ->placeholder('Sin teléfono'),

                        TextEntry::make('email')
                            ->label('Correo electrónico')
                            ->icon('heroicon-o-envelope')
                            ->copyable()
                            ->copyMessage('Correo copiado')
                            ->placeholder('Sin correo'),

                        TextEntry::make('sunat_environment')
                            ->label('Entorno SUNAT')
                            ->badge()
                            ->formatStateUsing(fn(?string $state): string => match ($state) {
                                'production' => 'Producción',
                                'beta' => 'Pruebas BETA',
                                default => 'No configurado',
                            })
                            ->color(fn(?string $state): string => match ($state) {
                                'production' => 'success',
                                'beta' => 'warning',
                                default => 'gray',
                            }),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->collapsible(),
            ]);
    }
}
