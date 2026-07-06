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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Support\Enums\MaxWidth;

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
        $user = Auth::user();

        if (! $user || ! $user->tenant || ! $user->tenant->businessSector) {
            return false;
        }

        $features = $user->tenant->businessSector->features ?? [];

        return (bool) ($features['has_rooms'] ?? false)
            && ! $user->hasRole('Vendedor');
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->isAdmin()
            && $record->status !== Room::STATUS_OCCUPIED;
    }

    public static function canDeleteAny(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canRestore(Model $record): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canRestoreAny(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()
            ->with(['zone'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        if (! $user || ! $user->tenant_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('tenant_id', $user->tenant_id);
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
                                            ->placeholder('Ej: 101, 201, Suite 1')
                                            ->helperText('Usa un identificador corto y fácil de reconocer en recepción.')
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
                                            ->native(false)
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
                                            ->minValue(0)
                                            ->step(0.10)
                                            ->required()
                                            ->default(0.00),

                                        Forms\Components\Select::make('status')
                                            ->label('Estado Operativo')
                                            ->options(Room::getStatuses())
                                            ->default(Room::STATUS_AVAILABLE)
                                            ->required()
                                            ->native(false)
                                            ->selectablePlaceholder(false)
                                            ->helperText('Este estado se actualiza también desde recepción durante check-in, check-out y limpieza.'),
                                    ]),
                            ])
                            // Le decimos que ocupe 1 de las 3 columnas en cualquier pantalla de PC
                            ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 1, 'lg' => 1, 'xl' => 1]),
                    ]),
            ]);
        // Ya no necesitamos el ->columns() aquí afuera, el Grid de arriba tiene el control absoluto.
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Detalle de la Habitación')
                    ->icon('heroicon-o-key')
                    ->description('Información general, ubicación, tarifa y estado operativo.')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Habitación')
                            ->weight('black')
                            ->icon('heroicon-o-key')
                            ->columnSpanFull(),

                        TextEntry::make('type')
                            ->label('Tipo')
                            ->badge()
                            ->color('info')
                            ->placeholder('Sin tipo'),

                        TextEntry::make('zone.name')
                            ->label('Piso / Zona')
                            ->badge()
                            ->color('gray')
                            ->placeholder('Sin piso asignado'),

                        TextEntry::make('price_per_night')
                            ->label('Tarifa por noche')
                            ->money('PEN')
                            ->icon('heroicon-o-banknotes'),

                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn(string $state): string => Room::getStatuses()[$state] ?? $state)
                            ->color(fn(string $state): string => match ($state) {
                                Room::STATUS_AVAILABLE => 'success',
                                Room::STATUS_OCCUPIED => 'danger',
                                Room::STATUS_DIRTY => 'warning',
                                Room::STATUS_MAINTENANCE => 'gray',
                                default => 'primary',
                            }),

                        IconEntry::make('deleted_at')
                            ->label('En papelera')
                            ->boolean()
                            ->state(fn(Room $record): bool => filled($record->deleted_at))
                            ->trueIcon('heroicon-o-trash')
                            ->falseIcon('heroicon-o-check-circle')
                            ->trueColor('danger')
                            ->falseColor('success'),

                        TextEntry::make('description')
                            ->label('Descripción / comodidades')
                            ->placeholder('Sin descripción')
                            ->columnSpanFull(),

                        TextEntry::make('last_cleaned_at')
                            ->label('Última limpieza')
                            ->dateTime('d/m/Y h:i A')
                            ->placeholder('Sin registro'),

                        TextEntry::make('created_at')
                            ->label('Creada')
                            ->dateTime('d/m/Y h:i A'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->recordUrl(null)
            ->recordAction('view')
            ->columns([
                Tables\Columns\TextColumn::make('mobile_summary')
                    ->label('Habitación')
                    ->state(fn(Room $record): string => $record->name)
                    ->description(function (Room $record): string {
                        $tipo = $record->type ?: 'Sin tipo';
                        $piso = $record->zone?->name ?? 'Sin piso';
                        $estado = Room::getStatuses()[$record->status] ?? $record->status;
                        $tarifa = 'S/ ' . number_format((float) $record->price_per_night, 2);

                        return "{$tipo} · {$piso} · {$estado} · {$tarifa}";
                    })
                    ->icon('heroicon-o-key')
                    ->weight('black')
                    ->wrap()
                    ->searchable(['name', 'type'])
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Habitación')
                    ->searchable()
                    ->weight('bold')
                    ->sortable()
                    ->description(fn(Room $record): string => $record->type)
                    ->visibleFrom('md'),

                // 🌟 NUEVO: Agregamos la columna de Piso/Zona a la tabla
                Tables\Columns\TextColumn::make('zone.name')
                    ->label('Piso / Zona')
                    ->sortable()
                    ->searchable()
                    ->badge() // Le da un estilo de etiqueta agradable
                    ->color('gray')
                    ->toggleable()
                    ->visibleFrom('md'), // Se puede ocultar si el usuario quiere

                Tables\Columns\TextColumn::make('price_per_night')
                    ->label('Tarifa')
                    ->money('PEN', true)
                    ->sortable()
                    ->color('success')
                    ->weight('semibold')
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => Room::getStatuses()[$state] ?? $state)
                    ->color(fn(string $state): string => match ($state) {
                        Room::STATUS_AVAILABLE => 'success', // Verde
                        Room::STATUS_OCCUPIED => 'danger',   // Rojo
                        Room::STATUS_DIRTY => 'warning',     // Amarillo/Naranja
                        Room::STATUS_MAINTENANCE => 'gray',  // Gris
                        default => 'primary',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        Room::STATUS_AVAILABLE => 'heroicon-m-check-circle',
                        Room::STATUS_OCCUPIED => 'heroicon-m-lock-closed',
                        Room::STATUS_DIRTY => 'heroicon-m-sparkles',
                        Room::STATUS_MAINTENANCE => 'heroicon-m-wrench-screwdriver',
                        default => 'heroicon-m-information-circle',
                    })
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('last_cleaned_at')
                    ->label('Últ. Limpieza')
                    ->dateTime('d/m/Y h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()
                    ->label('Papelera'),

                // Filtro rápido por estados
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Room::getStatuses())
                    ->native(false),

                // Filtro rápido por tipo
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'Simple' => 'Simple',
                        'Doble' => 'Doble',
                        'Matrimonial' => 'Matrimonial',
                        'Suite' => 'Suite',
                        'Familiar' => 'Familiar',
                    ])
                    ->native(false),

                // 🌟 NUEVO: Filtro por Piso
                Tables\Filters\SelectFilter::make('zone_id')
                    ->label('Piso / Zona')
                    ->relationship('zone', 'name', fn($query) => $query->where('tenant_id', Auth::user()->tenant_id))
                    ->searchable()
                    ->preload(),
            ])
            ->filtersFormWidth(MaxWidth::FourExtraLarge)
            ->filtersFormColumns([
                'default' => 1,
                'sm' => 2,
                'lg' => 4,
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Ver detalles')
                        ->icon('heroicon-o-eye')
                        ->color('info'),

                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil')
                        ->color('warning'),

                    Tables\Actions\DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->visible(fn(Room $record): bool => $record->status !== Room::STATUS_OCCUPIED)
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar habitación')
                        ->modalDescription('La habitación se moverá a la papelera. No se eliminará definitivamente.'),

                    Tables\Actions\RestoreAction::make()
                        ->label('Restaurar')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('success')
                        ->requiresConfirmation(),
                ])
                    ->label('Acciones')
                    ->tooltip('Acciones')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
                    ->color('gray'),
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
