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

class ZoneResource extends Resource
{
    protected static ?string $model = Zone::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Catálogos';

    // 🌟 Nombres genéricos para que aplique a ambos módulos
    protected static ?string $modelLabel = 'Zona / Piso';
    protected static ?string $pluralModelLabel = 'Zonas y Pisos';
    protected static ?int $navigationSort = 4;

    // 🌟 TRAEMOS EL CONTEO Y PERMITIMOS VER LA PAPELERA
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->withCount(['tables', 'rooms'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class, // Permite que el filtro de papelera funcione
            ]);
    }

    // 🌟 MAGIA SAAS: Visible si tiene Mesas O Habitaciones
    public static function canViewAny(): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = Auth::user();
        $features = $user->tenant->businessSector->features ?? [];

        $hasTables = $features['has_tables'] ?? false;
        $hasRooms = $features['has_rooms'] ?? false;

        // Si tiene cualquiera de los dos módulos y no es vendedor puro
        return ($hasTables || $hasRooms) && !$user->hasRole('Vendedor');
    }

    // =========================================================
    // 🔐 SEGURIDAD: Solo Administradores pueden gestionar Zonas
    // =========================================================
    public static function canCreate(): bool { return Auth::user()->isAdmin(); }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return Auth::user()->isAdmin(); }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return Auth::user()->isAdmin(); }
    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool { return Auth::user()->isAdmin(); }
    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; } // Nadie borra definitivamente
    public static function canDeleteAny(): bool { return Auth::user()->isAdmin(); }
    public static function canRestoreAny(): bool { return Auth::user()->isAdmin(); }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Espacio')
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
                            ->default(true)
                            ->columnSpan(['default' => 1, 'md' => 1]),
                    ])->columns(['default' => 1, 'md' => 3]),

                // 🌟 MAGIA UX: Esta sección SOLO aparece si el cliente tiene módulo de restaurante
                Forms\Components\Section::make('Distribución de Mesas')
                    ->visible(fn () => Auth::user()->tenant->businessSector->features['has_tables'] ?? false)
                    ->schema([
                        Forms\Components\Repeater::make('tables')
                            ->relationship()
                            ->label('')
                            ->addActionLabel('Agregar nueva mesa')
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                $data['tenant_id'] = Auth::user()->tenant_id;
                                $data['status'] = Table::STATUS_AVAILABLE;
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

    public static function table(FilamentTable $table): FilamentTable
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Zona / Piso')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // 🌟 COLUMNA DINÁMICA: Solo se muestra si tiene restaurante
                Tables\Columns\TextColumn::make('tables_count')
                    ->label('Total Mesas')
                    ->badge()
                    ->color('info')
                    ->visible(fn () => Auth::user()->tenant->businessSector->features['has_tables'] ?? false),

                // 🌟 COLUMNA DINÁMICA: Solo se muestra si tiene hotel
                Tables\Columns\TextColumn::make('rooms_count')
                    ->label('Habitaciones')
                    ->badge()
                    ->color('success')
                    ->visible(fn () => Auth::user()->tenant->businessSector->features['has_rooms'] ?? false),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Activo'),

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
                ->icon('heroicon-o-ellipsis-vertical')
                ->button()
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
