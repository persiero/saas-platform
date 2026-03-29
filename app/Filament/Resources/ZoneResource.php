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

class ZoneResource extends Resource
{
    protected static ?string $model = Zone::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $modelLabel = 'Zona y Mesas';
    protected static ?string $pluralModelLabel = 'Zonas y Mesas';
    protected static ?int $navigationSort = 3;

    // 🌟 MAGIA SAAS: Solo se muestra si el negocio es Restaurante (has_tables = true)
    public static function canViewAny(): bool
    {
        $features = Auth::user()->tenant->businessSector->features ?? [];
        return $features['has_tables'] ?? false;
    }

    // 🌟 SEGURIDAD SAAS: Solo trae las zonas de ESTE inquilino
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->withCount('tables'); // Trae el conteo de mesas para mostrar en la tabla
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Zona')
                    ->description('Crea un ambiente (Ej: Salón, Terraza) y agrégale sus mesas.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la Zona')
                            ->placeholder('Ej: Salón Principal')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(['default' => 1, 'md' => 2]),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Zona Activa')
                            ->default(true)
                            ->columnSpan(['default' => 1, 'md' => 1]),
                    ])->columns(['default' => 1, 'md' => 3]),

                Forms\Components\Section::make('Distribución de Mesas')
                    ->schema([
                        // 🌟 MAGIA UX: Repetidor Relacional (Crea las mesas directamente aquí)
                        Forms\Components\Repeater::make('tables')
                            ->relationship() // Laravel detecta automáticamente la relación "tables" del modelo Zone
                            ->label('')
                            ->addActionLabel('Agregar nueva mesa')
                            // INYECTAMOS EL TENANT_ID A CADA MESA NUEVA ANTES DE GUARDAR
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                $data['tenant_id'] = Auth::user()->tenant_id;
                                $data['status'] = Table::STATUS_AVAILABLE; // Por defecto nace libre
                                return $data;
                            })
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre / Número')
                                    ->placeholder('Ej: Mesa 1')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 1, 'md' => 2]),

                                Forms\Components\TextInput::make('capacity')
                                    ->label('Sillas (Capacidad)')
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
                            ->defaultItems(1) // Siempre muestra al menos una fila vacía para llenar
                            ->reorderable(false) // Desactivamos el reordenamiento para no complicar la BD por ahora
                            ->collapsible(),
                    ]),
            ]);
    }

    public static function table(FilamentTable $table): FilamentTable
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Zona')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('tables_count')
                    ->label('Total Mesas')
                    ->badge()
                    ->color('info'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Activo'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListZones::route('/'),
            'create' => Pages\CreateZone::route('/create'),
            'edit' => Pages\EditZone::route('/{record}/edit'),
        ];
    }
}
