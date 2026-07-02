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

class DeliveryZoneResource extends Resource
{
    protected static ?string $model = DeliveryZone::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $modelLabel = 'Zona de Reparto';
    protected static ?string $pluralModelLabel = 'Zonas de Repartos';
    protected static ?int $navigationSort = 4;

    private static function canAccessDeliveryZones(): bool
    {
        /** @var \Percy\Core\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->tenant_id === null) {
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
                    ->description('Selecciona el distrito donde ofrecerás servicio de delivery.')
                    ->schema([
                        // 🌟 SELECT DE DEPARTAMENTO (No se guarda, solo filtra)
                        Forms\Components\Select::make('department_id')
                            ->label('Departamento')
                            ->options(Department::pluck('name', 'id'))
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(fn(callable $set) => $set('province_id', null)),

                        // 🌟 SELECT DE PROVINCIA (No se guarda, solo filtra)
                        Forms\Components\Select::make('province_id')
                            ->label('Provincia')
                            ->options(function (callable $get) {
                                $deptId = $get('department_id');
                                if (!$deptId) return [];
                                return Province::where('department_id', $deptId)->pluck('name', 'id');
                            })
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(fn(callable $set) => $set('district_id', null)),

                        // 🌟 SELECT DE DISTRITO (Este SÍ se guarda en district_id)
                        Forms\Components\Select::make('district_id')
                            ->label('Distrito de Cobertura')
                            ->options(function (callable $get) {
                                $provId = $get('province_id');
                                if (!$provId) return [];
                                return District::where('province_id', $provId)->pluck('name', 'id');
                            })
                            ->required()
                            ->unique(ignorable: fn($record) => $record, modifyRuleUsing: function ($rule) {
                                return $rule->where('tenant_id', Auth::user()->tenant_id);
                            })
                            ->helperText('Solo puedes agregar un distrito una vez.'),
                    ])->columns(3),

                Forms\Components\Section::make('Configuración de Tarifa')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Costo de Envío')
                            ->prefix('S/')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('estimated_time')
                            ->label('Tiempo Estimado')
                            ->placeholder('Ej: 30-45 min')
                            ->maxLength(50),

                        Forms\Components\Toggle::make('is_active')
                            ->label('¿Zona Activa?')
                            ->default(true)
                            ->helperText('Si se desactiva, los clientes no podrán elegir este distrito.'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('district.name')
                    ->label('Distrito')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('district.province.name')
                    ->label('Provincia')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Costo')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('estimated_time')
                    ->label('Entrega'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Estado')
                    ->boolean(),
            ])
            ->filters([
                // Filtro por local (Tenant) automático
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    // 🌟 MULTI-TENANT: Solo muestra las zonas del negocio logueado
    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        if (! $user || $user->tenant_id === null) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->where('tenant_id', $user->tenant_id);
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
