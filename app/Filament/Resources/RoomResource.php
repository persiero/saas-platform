<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoomResource\Pages;
use Percy\Core\Models\Room;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $navigationIcon = 'heroicon-o-key'; // Un ícono perfecto para hoteles
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?string $modelLabel = 'Habitación';
    protected static ?string $pluralModelLabel = 'Habitaciones';
    protected static ?int $navigationSort = 4;

    /**
     * 🌟 SEGURIDAD MULTI-TENANT: ¿Quién puede ver este módulo?
     * Solo los negocios que tengan la característica 'has_rooms' activada (Hoteles).
     */
    public static function canViewAny(): bool
    {
        $features = Auth::user()->tenant->businessSector->features ?? [];
        return $features['has_rooms'] ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // 🌟 EL TRUCO: Envolvemos TODO en un Grid maestro y forzamos las columnas en todos los tamaños de pantalla
                Forms\Components\Grid::make(['default' => 1, 'sm' => 1, 'md' => 3, 'lg' => 3, 'xl' => 3])
                    ->schema([

                        // 🌟 UX: Columna Izquierda (Ocupa 2/3 en PC, 1/1 en Celular)
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Section::make('Detalles de la Habitación')
                                    ->columns(['default' => 1, 'sm' => 2])
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Identificador / Número')
                                            ->placeholder('Ej: Habitación 201')
                                            ->required()
                                            ->maxLength(255),

                                        Forms\Components\Select::make('type')
                                            ->label('Tipo de Habitación')
                                            ->options([
                                                'Simple' => 'Simple (1 Cama)',
                                                'Doble' => 'Doble (2 Camas)',
                                                'Matrimonial' => 'Matrimonial',
                                                'Suite' => 'Suite Principal',
                                                'Familiar' => 'Familiar',
                                            ])
                                            ->required()
                                            ->searchable(),

                                        Forms\Components\Textarea::make('description')
                                            ->label('Descripción y Comodidades')
                                            ->placeholder('Ej: Vista al mar, TV 50", Frigobar, Jacuzzi...')
                                            ->rows(4)
                                            ->columnSpanFull(),

                                        // 📸 BONUS UX: (Subida de Imágenes)
                                        // Si en el futuro agregas una columna 'image' a tu tabla 'rooms' en la base de datos,
                                        // solo tienes que descomentar este bloque para que puedan subir fotos hermosas de las habitaciones.
                                        /*
                                        Forms\Components\FileUpload::make('image')
                                            ->label('Fotografía de la Habitación')
                                            ->image()
                                            ->imageEditor() // Permite recortar la foto directamente en el navegador
                                            ->directory('rooms')
                                            ->columnSpanFull()
                                            ->helperText('Sube una imagen representativa para el monitor de recepción.'),
                                        */
                                    ]),
                            ])
                            // Le decimos que ocupe 2 de las 3 columnas en cualquier pantalla de PC
                            ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 2, 'lg' => 2, 'xl' => 2]),

                        // 🌟 UX: Columna Derecha (Ocupa 1/3 en PC, 1/1 en Celular)
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Section::make('Propiedades')
                                    ->schema([
                                        Forms\Components\Select::make('zone_id')
                                            ->label('Piso / Zona')
                                            ->relationship('zone', 'name', function ($query) {
                                                return $query->where('tenant_id', Auth::user()->tenant_id)
                                                             ->where('is_active', true);
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Seleccione un piso (Opcional)')
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('Nombre del Piso / Zona')
                                                    ->placeholder('Ej: Piso 1, Zona VIP')
                                                    ->required()
                                                    ->maxLength(255),
                                                Forms\Components\Toggle::make('is_active')
                                                    ->label('Activo')
                                                    ->default(true),
                                            ])
                                            ->createOptionUsing(function (array $data) {
                                                $data['tenant_id'] = Auth::user()->tenant_id;
                                                $zone = \Percy\Core\Models\Zone::create($data);
                                                return $zone->id;
                                            }),

                                        Forms\Components\TextInput::make('price_per_night')
                                            ->label('Tarifa por Noche')
                                            ->numeric()
                                            ->prefix('S/')
                                            ->required()
                                            ->default(0.00),

                                        Forms\Components\Select::make('status')
                                            ->label('Estado Operativo')
                                            ->options(Room::getStatuses())
                                            ->default(Room::STATUS_AVAILABLE)
                                            ->required()
                                            ->native(false)
                                            ->selectablePlaceholder(false),
                                    ]),
                            ])
                            // Le decimos que ocupe 1 de las 3 columnas en cualquier pantalla de PC
                            ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 1, 'lg' => 1, 'xl' => 1]),
                    ]),
            ]);
            // Ya no necesitamos el ->columns() aquí afuera, el Grid de arriba tiene el control absoluto.
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Habitación')
                    ->searchable()
                    ->weight('bold')
                    ->sortable()
                    ->description(fn (Room $record): string => $record->type),

                // 🌟 NUEVO: Agregamos la columna de Piso/Zona a la tabla
                Tables\Columns\TextColumn::make('zone.name')
                    ->label('Piso / Zona')
                    ->sortable()
                    ->searchable()
                    ->badge() // Le da un estilo de etiqueta agradable
                    ->color('gray')
                    ->toggleable(), // Se puede ocultar si el usuario quiere

                Tables\Columns\TextColumn::make('price_per_night')
                    ->label('Tarifa')
                    ->money('PEN', true)
                    ->sortable()
                    ->color('success')
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Room::getStatuses()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Room::STATUS_AVAILABLE => 'success', // Verde
                        Room::STATUS_OCCUPIED => 'danger',   // Rojo
                        Room::STATUS_DIRTY => 'warning',     // Amarillo/Naranja
                        Room::STATUS_MAINTENANCE => 'gray',  // Gris
                        default => 'primary',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        Room::STATUS_AVAILABLE => 'heroicon-m-check-circle',
                        Room::STATUS_OCCUPIED => 'heroicon-m-lock-closed',
                        Room::STATUS_DIRTY => 'heroicon-m-sparkles',
                        Room::STATUS_MAINTENANCE => 'heroicon-m-wrench-screwdriver',
                        default => 'heroicon-m-information-circle',
                    }),

                Tables\Columns\TextColumn::make('last_cleaned_at')
                    ->label('Últ. Limpieza')
                    ->dateTime('d/m/Y h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filtro rápido por estados
                Tables\Filters\SelectFilter::make('status')
                    ->label('Filtrar por Estado')
                    ->options(Room::getStatuses()),

                // Filtro rápido por tipo
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'Simple' => 'Simple',
                        'Doble' => 'Doble',
                        'Matrimonial' => 'Matrimonial',
                        'Suite' => 'Suite',
                        'Familiar' => 'Familiar',
                    ]),

                // 🌟 NUEVO: Filtro por Piso
                Tables\Filters\SelectFilter::make('zone_id')
                    ->label('Filtrar por Piso')
                    ->relationship('zone', 'name', fn ($query) => $query->where('tenant_id', Auth::user()->tenant_id)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No hay habitaciones registradas')
            ->emptyStateDescription('Comienza agregando las habitaciones de tu establecimiento.')
            ->emptyStateIcon('heroicon-o-building-office');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRooms::route('/'),
            'create' => Pages\CreateRoom::route('/create'),
            'edit' => Pages\EditRoom::route('/{record}/edit'),
        ];
    }
}
