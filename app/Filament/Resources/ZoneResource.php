<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ZoneResource\Pages;
use App\Filament\Resources\ZoneResource\RelationManagers;
use Percy\Core\Models\Zone;
use Percy\Core\Models\Table;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table as FilamentTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Infolists\Infolist;
use App\Filament\Resources\ZoneResource\Schemas\ZoneInfolist;

class ZoneResource extends Resource
{
    protected static ?string $model = Zone::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationGroup = 'Catálogos';

    // 🌟 Nombres genéricos para que aplique a ambos módulos
    protected static ?string $modelLabel = 'Zona / Piso';
    protected static ?string $pluralModelLabel = 'Zonas y Pisos';
    protected static ?int $navigationSort = 4;

    // 🌟 TRAEMOS EL CONTEO Y PERMITIMOS VER LA PAPELERA
    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()
            ->withCount(['tables', 'rooms'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        if (! $user || ! $user->tenant_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('tenant_id', $user->tenant_id);
    }

    // 🌟 MAGIA SAAS: Visible si tiene Mesas O Habitaciones
    public static function canViewAny(): bool
    {
        /** @var \Percy\Core\Models\User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->tenant || ! $user->tenant->businessSector) {
            return false;
        }

        $features = $user->tenant->businessSector->features ?? [];

        $hasTables = (bool) ($features['has_tables'] ?? false);
        $hasRooms = (bool) ($features['has_rooms'] ?? false);

        return ($hasTables || $hasRooms)
            && ! $user->hasRole('Vendedor');
    }

    // =========================================================
    // 🔐 SEGURIDAD: Solo Administradores pueden gestionar Zonas
    // =========================================================
    public static function canCreate(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canRestoreAny(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Espacio')
                    ->icon('heroicon-o-map')
                    ->description('Crea un ambiente (Ej: Salón, Terraza, Piso 1, Bungalows).')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la Zona o Piso')
                            ->placeholder('Ej: Salón Principal o Piso 1')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(['default' => 1, 'md' => 2]),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->helperText('Desactívalo si esta zona o piso no estará disponible temporalmente.')
                            ->inline(false)
                            ->default(true)
                            ->columnSpan(['default' => 1, 'md' => 1]),
                    ])->columns(['default' => 1, 'md' => 3]),

                // 🌟 MAGIA UX: Esta sección SOLO aparece si el cliente tiene módulo de restaurante
                Forms\Components\Section::make('Distribución de Mesas')
                    ->icon('heroicon-o-squares-2x2')
                    ->description('Configura las mesas disponibles dentro de esta zona o salón.')
                    ->visible(fn(): bool => (bool) (Auth::user()?->tenant?->businessSector?->features['has_tables'] ?? false))
                    ->schema([
                        Forms\Components\Repeater::make('tables')
                            ->relationship()
                            ->label('')
                            ->addActionLabel('Agregar mesa')
                            ->itemLabel(fn(array $state): ?string => $state['name'] ?? 'Nueva mesa')
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                $data['tenant_id'] = Auth::user()->tenant_id;

                                if (blank($data['status'] ?? null)) {
                                    $data['status'] = Table::STATUS_AVAILABLE;
                                }

                                return $data;
                            })
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre / Número')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 1, 'md' => 2]),

                                Forms\Components\TextInput::make('capacity')
                                    ->label('Sillas')
                                    ->numeric()
                                    ->default(4)
                                    ->minValue(1)
                                    ->required()
                                    ->columnSpan(['default' => 1, 'md' => 1]),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Habilitada')
                                    ->default(true)
                                    ->columnSpan(['default' => 1, 'md' => 1]),
                            ])
                            ->columns(['default' => 1, 'md' => 4])
                            ->defaultItems(1)
                            ->reorderable(false)
                            ->collapsible(),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return ZoneInfolist::configure($infolist);
    }

    public static function table(FilamentTable $table): FilamentTable
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
                    ->label('Zona / Piso')
                    ->state(fn(Zone $record): string => $record->name)
                    ->description(function (Zone $record): string {
                        $features = Auth::user()?->tenant?->businessSector?->features ?? [];

                        $parts = [];

                        if ($features['has_tables'] ?? false) {
                            $parts[] = "{$record->tables_count} mesas";
                        }

                        if ($features['has_rooms'] ?? false) {
                            $parts[] = "{$record->rooms_count} habitaciones";
                        }

                        $parts[] = $record->is_active ? 'Activo' : 'Inactivo';

                        return implode(' · ', $parts);
                    })
                    ->icon('heroicon-o-map')
                    ->weight('black')
                    ->wrap()
                    ->searchable(['name'])
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Zona / Piso')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->visibleFrom('md'),

                // 🌟 COLUMNA DINÁMICA: Solo se muestra si tiene restaurante
                Tables\Columns\TextColumn::make('tables_count')
                    ->label('Total Mesas')
                    ->badge()
                    ->color('info')
                    ->visible(fn() => Auth::user()->tenant->businessSector->features['has_tables'] ?? false)
                    ->visibleFrom('md'),

                // 🌟 COLUMNA DINÁMICA: Solo se muestra si tiene hotel
                Tables\Columns\TextColumn::make('rooms_count')
                    ->label('Habitaciones')
                    ->badge()
                    ->color('success')
                    ->visible(fn() => Auth::user()->tenant->businessSector->features['has_rooms'] ?? false)
                    ->visibleFrom('md'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Activo')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // 🌟 FILTRO DE PAPELERA
                TrashedFilter::make(),
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

                    Tables\Actions\DeleteAction::make() // Borrado lógico
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Zona')
                        ->modalDescription('¿Estás seguro de que deseas eliminar esta zona? Las ventas históricas se mantendrán, pero ya no estará disponible para nuevas operaciones.'),

                    Tables\Actions\RestoreAction::make()
                        ->label('Restaurar')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Restaurar Zona')
                        ->modalDescription('¿Deseas rescatar esta zona de la papelera? Sus mesas/habitaciones volverán a estar activas.'),
                ])
                    ->label('Acciones')
                    ->tooltip('Acciones')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
                    ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->requiresConfirmation(),
                    Tables\Actions\RestoreBulkAction::make(), // Restaurar masivo
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListZones::route('/'),
            'create' => Pages\CreateZone::route('/create'),
            'edit' => Pages\EditZone::route('/{record}/edit'),
        ];
    }
}
