<?php

namespace App\Filament\Resources\ZoneResource\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Models\Zone;

class ZoneInfolist
{
    public static function configure(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Detalle de la Zona / Piso')
                    ->icon('heroicon-o-map')
                    ->description('Resumen del espacio configurado para restaurante u hotel.')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nombre')
                            ->weight('black')
                            ->icon('heroicon-o-map')
                            ->columnSpanFull(),

                        TextEntry::make('tables_count')
                            ->label('Mesas')
                            ->badge()
                            ->color('info')
                            ->visible(fn(): bool => (bool) (Auth::user()?->tenant?->businessSector?->features['has_tables'] ?? false)),

                        TextEntry::make('rooms_count')
                            ->label('Habitaciones')
                            ->badge()
                            ->color('success')
                            ->visible(fn(): bool => (bool) (Auth::user()?->tenant?->businessSector?->features['has_rooms'] ?? false)),

                        IconEntry::make('is_active')
                            ->label('Estado')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
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

                Section::make('Mesas configuradas')
                    ->icon('heroicon-o-squares-2x2')
                    ->visible(fn(): bool => (bool) (Auth::user()?->tenant?->businessSector?->features['has_tables'] ?? false))
                    ->schema([
                        TextEntry::make('tables_summary')
                            ->label('Mesas')
                            ->state(function (Zone $record): string {
                                $record->loadMissing('tables');

                                if ($record->tables->isEmpty()) {
                                    return 'No hay mesas configuradas.';
                                }

                                return $record->tables
                                    ->map(fn($table): string => "{$table->name} ({$table->capacity} sillas)")
                                    ->join(', ');
                            })
                            ->columnSpanFull()
                            ->placeholder('No hay mesas configuradas.'),
                    ])
                    ->collapsible(),
            ]);
    }
}
