<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SerieResource\Pages;
use Percy\Core\Models\Serie;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Services\Tenants\TenantPlanService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;

class SerieResource extends Resource
{
    protected static ?string $model = Serie::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $modelLabel = 'Serie de Comprobante';
    protected static ?string $pluralModelLabel = 'Series de Comprobantes';
    protected static ?int $navigationSort = 2;

    private static function tenantPlanHas(string $feature): bool
    {
        /** @var \Percy\Core\Models\User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->tenant) {
            return false;
        }

        return app(TenantPlanService::class)->has($feature, $user->tenant);
    }

    private static function userCanManageSeries(): bool
    {
        /** @var \Percy\Core\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if (! $user->tenant_id) {
            return false;
        }

        return $user->isAdmin()
            && self::tenantPlanHas('has_internal_sales');
    }

    private static function allowedDocumentTypesByPlan(): array
    {
        if (! self::tenantPlanHas('has_sunat')) {
            return [
                '00' => 'Nota de Venta (Ticket Interno)',
            ];
        }

        $options = [
            '00' => 'Nota de Venta (Ticket Interno)',
            '03' => 'Boleta Electrónica',
            '01' => 'Factura Electrónica',
        ];

        if (self::tenantPlanHas('has_credit_notes')) {
            $options['07'] = 'Nota de Crédito';
        }

        if (self::tenantPlanHas('has_debit_notes')) {
            $options['08'] = 'Nota de Débito';
        }

        return $options;
    }

    public static function canViewAny(): bool
    {
        return self::userCanManageSeries();
    }

    public static function canCreate(): bool
    {
        return self::userCanManageSeries();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::userCanManageSeries()
            && array_key_exists($record->document_type, self::allowedDocumentTypesByPlan());
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::userCanManageSeries()
            && array_key_exists($record->document_type, self::allowedDocumentTypesByPlan())
            && (int) $record->correlative === 0;
    }

    public static function canDeleteAny(): bool
    {
        return self::userCanManageSeries();
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery();

        if (! $user || ! $user->tenant_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('document_type', array_keys(self::allowedDocumentTypesByPlan()));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la Serie')
                    ->description('Configura la serie que se usará para emitir tickets internos, boletas, facturas o notas electrónicas.')
                    ->icon('heroicon-o-document-duplicate')
                    ->schema([
                        Forms\Components\Select::make('document_type')
                            ->label('Tipo de comprobante')
                            ->options(fn(): array => self::allowedDocumentTypesByPlan())
                            ->required()
                            ->native(false)
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('serie')
                            ->label('Serie')
                            ->placeholder('Ej: F001, B001, N001, T001')
                            ->required()
                            ->length(4)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->dehydrateStateUsing(fn(?string $state): ?string => filled($state) ? strtoupper(trim($state)) : null)
                            ->rule(function (?Serie $record, \Filament\Forms\Get $get) {
                                return Rule::unique('series', 'serie')
                                    ->where('tenant_id', Auth::user()->tenant_id)
                                    ->where('document_type', $get('document_type'))
                                    ->ignore($record?->id);
                            })
                            ->rules([
                                fn(\Filament\Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $type = $get('document_type');
                                    $value = strtoupper((string) $value);

                                    if (! array_key_exists($type, self::allowedDocumentTypesByPlan())) {
                                        $fail('Tu plan actual no permite crear series para este tipo de comprobante.');
                                        return;
                                    }

                                    if ($type === '01' && ! str_starts_with($value, 'F')) {
                                        $fail('La serie para Facturas debe empezar con F. Ejemplo: F001.');
                                    }

                                    if ($type === '03' && ! str_starts_with($value, 'B')) {
                                        $fail('La serie para Boletas debe empezar con B. Ejemplo: B001.');
                                    }

                                    if (in_array($type, ['07', '08'], true) && ! in_array(substr($value, 0, 1), ['F', 'B'], true)) {
                                        $fail('Las notas deben empezar con F o B, según el comprobante relacionado.');
                                    }

                                    if ($type === '00' && ! str_starts_with($value, 'N') && ! str_starts_with($value, 'T')) {
                                        $fail('La serie para Notas de Venta debe empezar con N o T. Ejemplo: N001 o T001.');
                                    }
                                },
                            ]),

                        Forms\Components\TextInput::make('correlative')
                            ->label('Último número emitido')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->default(0)
                            ->helperText('Coloca 0 si es una serie nueva. El siguiente comprobante será 00000001.'),

                        Forms\Components\Toggle::make('active')
                            ->label('Serie activa')
                            ->helperText('Solo las series activas estarán disponibles al emitir comprobantes.')
                            ->inline(false)
                            ->default(true)
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Detalle de la Serie')
                    ->icon('heroicon-o-document-duplicate')
                    ->description('Información del comprobante, correlativo actual y siguiente número disponible.')
                    ->schema([
                        TextEntry::make('document_type')
                            ->label('Tipo de comprobante')
                            ->badge()
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                '01' => 'Factura Electrónica',
                                '03' => 'Boleta Electrónica',
                                '07' => 'Nota de Crédito',
                                '08' => 'Nota de Débito',
                                '00' => 'Nota de Venta Interna',
                                default => $state,
                            })
                            ->color(fn(string $state): string => match ($state) {
                                '01' => 'success',
                                '03' => 'info',
                                '07' => 'warning',
                                '08' => 'danger',
                                '00' => 'gray',
                                default => 'gray',
                            }),

                        TextEntry::make('serie')
                            ->label('Serie')
                            ->badge()
                            ->color('gray')
                            ->copyable()
                            ->copyMessage('Serie copiada'),

                        TextEntry::make('correlative')
                            ->label('Último número emitido'),

                        TextEntry::make('next_number')
                            ->label('Siguiente comprobante')
                            ->state(function (Serie $record): string {
                                $nextCorrelative = (int) $record->correlative + 1;

                                return $record->serie . '-' . str_pad($nextCorrelative, 8, '0', STR_PAD_LEFT);
                            })
                            ->copyable()
                            ->copyMessage('Siguiente comprobante copiado'),

                        IconEntry::make('active')
                            ->label('Activa')
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
                    ->label('Serie')
                    ->state(fn(Serie $record): string => $record->serie)
                    ->description(function (Serie $record): string {
                        $tipo = match ($record->document_type) {
                            '01' => 'Factura',
                            '03' => 'Boleta',
                            '07' => 'Nota Crédito',
                            '08' => 'Nota Débito',
                            '00' => 'Nota de Venta',
                            default => $record->document_type,
                        };

                        $nextCorrelative = (int) $record->correlative + 1;
                        $siguiente = $record->serie . '-' . str_pad($nextCorrelative, 8, '0', STR_PAD_LEFT);
                        $estado = $record->active ? 'Activa' : 'Inactiva';

                        return "{$tipo} · Siguiente: {$siguiente} · {$estado}";
                    })
                    ->icon('heroicon-o-document-duplicate')
                    ->weight('black')
                    ->wrap()
                    ->searchable(['serie'])
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('document_type')
                    ->label('Tipo de comprobante')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        '01' => 'Factura',
                        '03' => 'Boleta',
                        '07' => 'Nota Crédito',
                        '08' => 'Nota Débito',
                        '00' => 'Nota de Venta', // 🌟 NUEVA ETIQUETA
                        default => $state,
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        '01' => 'success',
                        '03' => 'info',
                        '07' => 'warning',
                        '08' => 'danger',
                        '00' => 'gray', // 🌟 COLOR GRIS (Para indicar que no va a SUNAT)
                        default => 'gray',
                    })
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('serie')
                    ->label('Serie')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('correlative')
                    ->label('Último correlativo')
                    ->sortable()
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('next_number')
                    ->label('Siguiente comprobante')
                    ->state(function ($record) {
                        $nextCorrelative = $record->correlative + 1;

                        return $record->serie . '-' . str_pad($nextCorrelative, 8, '0', STR_PAD_LEFT);
                    })
                    ->visibleFrom('md'),

                Tables\Columns\ToggleColumn::make('active')
                    ->label('Activa')
                    ->visibleFrom('md'),

            ])
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Tipo')
                    ->options(fn(): array => self::allowedDocumentTypesByPlan())
                    ->native(false),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Estado')
                    ->placeholder('Todas')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas')
                    ->native(false),
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
                        ->modalWidth('2xl'),

                    Tables\Actions\DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn(Serie $record): bool => (int) $record->correlative === 0)
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar serie')
                        ->modalDescription('Solo puedes eliminar series que todavía no han emitido comprobantes.'),
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
            'index' => Pages\ListSeries::route('/'),
            //'create' => Pages\CreateSerie::route('/create'),
            //'edit' => Pages\EditSerie::route('/{record}/edit'),
        ];
    }
}
