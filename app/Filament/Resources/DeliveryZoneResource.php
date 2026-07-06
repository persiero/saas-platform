<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryZoneResource\Pages;
use Percy\Core\Models\DeliveryZone;
use Percy\Core\Models\Department;
use Percy\Core\Models\Province;
use Percy\Core\Models\District;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Services\Tenants\TenantPlanService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;

class DeliveryZoneResource extends Resource
{
    protected static ?string $model = DeliveryZone::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $modelLabel = 'Zona de Reparto';
    protected static ?string $pluralModelLabel = 'Zonas de Reparto';
    protected static ?int $navigationSort = 4;

    private static function canAccessDeliveryZones(): bool
    {
        /** @var \Percy\Core\Models\User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->tenant_id || ! $user->tenant) {
            return false;
        }

        if (! $user->isAdmin()) {
            return false;
        }

        return app(TenantPlanService::class)->has('has_delivery', $user->tenant)
            || app(TenantPlanService::class)->has('has_online_store', $user->tenant);
    }

    // 🌟 AGREGA ESTO AQUÍ:
    public static function canViewAny(): bool
    {
        return self::canAccessDeliveryZones();
    }

    public static function canCreate(): bool
    {
        return self::canAccessDeliveryZones();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::canAccessDeliveryZones();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::canAccessDeliveryZones();
    }

    public static function canDeleteAny(): bool
    {
        return self::canAccessDeliveryZones();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Ubicación Geográfica')
                    ->icon('heroicon-o-map-pin')
                    ->description('Selecciona el departamento, provincia y distrito donde ofrecerás servicio de delivery.')
                    ->schema([
                        Forms\Components\Select::make('department_id')
                            ->label('Departamento')
                            ->options(fn(): array => Department::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->required()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Forms\Set $set, ?DeliveryZone $record): void {
                                if (! $record?->district?->province?->department_id) {
                                    return;
                                }

                                $set('department_id', $record->district->province->department_id);
                                $set('province_id', $record->district->province_id);
                            })
                            ->afterStateUpdated(function (Forms\Set $set): void {
                                $set('province_id', null);
                                $set('district_id', null);
                            }),

                        Forms\Components\Select::make('province_id')
                            ->label('Provincia')
                            ->options(function (Forms\Get $get): array {
                                $departmentId = $get('department_id');

                                if (! $departmentId) {
                                    return [];
                                }

                                return Province::query()
                                    ->where('department_id', $departmentId)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->required()
                            ->dehydrated(false)
                            ->disabled(fn(Forms\Get $get): bool => blank($get('department_id')))
                            ->afterStateUpdated(fn(Forms\Set $set): mixed => $set('district_id', null)),

                        Forms\Components\Select::make('district_id')
                            ->label('Distrito de Cobertura')
                            ->options(function (Forms\Get $get): array {
                                $provinceId = $get('province_id');

                                if (! $provinceId) {
                                    return [];
                                }

                                return District::query()
                                    ->where('province_id', $provinceId)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->disabled(fn(Forms\Get $get): bool => blank($get('province_id')))
                            ->unique(
                                ignorable: fn(?DeliveryZone $record) => $record,
                                modifyRuleUsing: fn($rule) => $rule->where('tenant_id', Auth::user()->tenant_id)
                            )
                            ->helperText('Solo puedes agregar un distrito una vez por negocio.'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ]),

                Forms\Components\Section::make('Configuración de Tarifa')
                    ->icon('heroicon-o-truck')
                    ->description('Define el costo de envío y el tiempo estimado mostrado al cliente.')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Costo de Envío')
                            ->prefix('S/')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.10)
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('estimated_time')
                            ->label('Tiempo Estimado')
                            ->placeholder('Ej: 30-45 min')
                            ->maxLength(50),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Zona activa')
                            ->inline(false)
                            ->default(true)
                            ->helperText('Si se desactiva, los clientes no podrán elegir este distrito.'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Detalle de la Zona de Reparto')
                    ->icon('heroicon-o-truck')
                    ->description('Información del distrito cubierto, costo de envío y disponibilidad.')
                    ->schema([
                        TextEntry::make('district.name')
                            ->label('Distrito')
                            ->weight('black')
                            ->icon('heroicon-o-map-pin')
                            ->placeholder('Distrito no disponible'),

                        TextEntry::make('district.province.name')
                            ->label('Provincia')
                            ->placeholder('Sin provincia'),

                        TextEntry::make('district.province.department.name')
                            ->label('Departamento')
                            ->placeholder('Sin departamento'),

                        TextEntry::make('price')
                            ->label('Costo de envío')
                            ->money('PEN')
                            ->icon('heroicon-o-banknotes'),

                        TextEntry::make('estimated_time')
                            ->label('Tiempo estimado')
                            ->placeholder('Sin tiempo definido')
                            ->icon('heroicon-o-clock'),

                        IconEntry::make('is_active')
                            ->label('Estado')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
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
            ->modifyQueryUsing(fn(Builder $query): Builder => $query->with(['district.province.department']))
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
                    ->label('Zona')
                    ->state(fn(DeliveryZone $record): string => $record->district?->name ?? 'Distrito no disponible')
                    ->description(function (DeliveryZone $record): string {
                        $province = $record->district?->province?->name ?? 'Sin provincia';
                        $price = 'S/ ' . number_format((float) $record->price, 2);
                        $time = $record->estimated_time ?: 'Sin tiempo';
                        $status = $record->is_active ? 'Activa' : 'Inactiva';

                        return "{$province} · {$price} · {$time} · {$status}";
                    })
                    ->icon('heroicon-o-truck')
                    ->weight('black')
                    ->wrap()
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('district.name')
                    ->label('Distrito')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('district.province.name')
                    ->label('Provincia')
                    ->color('gray')
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('district.province.department.name')
                    ->label('Departamento')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('price')
                    ->label('Costo')
                    ->money('PEN')
                    ->sortable()
                    ->color('success')
                    ->weight('semibold')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('estimated_time')
                    ->label('Entrega')
                    ->placeholder('Sin tiempo')
                    ->visibleFrom('lg'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Estado')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->visibleFrom('md'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todas')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas')
                    ->native(false),

                Tables\Filters\SelectFilter::make('district_id')
                    ->label('Distrito')
                    ->relationship('district', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->filtersFormColumns([
                'default' => 1,
                'md' => 2,
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
                        ->color('warning')
                        ->slideOver()
                        ->modalWidth('3xl'),

                    Tables\Actions\DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar zona de reparto')
                        ->modalDescription('La zona dejará de estar disponible para pedidos online.'),
                ])
                    ->label('Acciones')
                    ->tooltip('Acciones')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
                    ->color('gray'),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No hay zonas de reparto')
            ->emptyStateDescription('Agrega distritos donde tu negocio realizará entregas a domicilio.')
            ->emptyStateIcon('heroicon-o-truck');
    }

    // 🌟 MULTI-TENANT: Solo muestra las zonas del negocio logueado
    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()
            ->with([
                'district.province.department',
            ]);

        if (! $user || ! $user->tenant_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('tenant_id', $user->tenant_id);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliveryZones::route('/'),
            'create' => Pages\CreateDeliveryZone::route('/create'),
            'edit' => Pages\EditDeliveryZone::route('/{record}/edit'),
        ];
    }
}
