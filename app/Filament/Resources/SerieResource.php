<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SerieResource\Pages;
use App\Filament\Resources\SerieResource\RelationManagers;
use Percy\Core\Models\Serie;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Services\Tenants\TenantPlanService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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

        if ($user->tenant_id === null) {
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
            && array_key_exists($record->document_type, self::allowedDocumentTypesByPlan());
    }

    public static function canDeleteAny(): bool
    {
        return self::userCanManageSeries();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereIn('document_type', array_keys(self::allowedDocumentTypesByPlan()));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('document_type')
                    ->label('Tipo de comprobante')
                    ->options(fn(): array => self::allowedDocumentTypesByPlan())
                    ->required()
                    ->native(false)
                    ->live() // Hace que reaccione al instante
                    ->columnSpan(2),

                Forms\Components\TextInput::make('serie')
                    ->label('Serie')
                    ->placeholder('Ej: F001, B001, N001, BC01, FC01')
                    ->required()
                    ->length(4)
                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                    // Regla de validación mágica:
                    ->rules([
                        fn(\Filament\Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                            $type = $get('document_type');
                            $value = strtoupper($value);

                            if (! array_key_exists($type, self::allowedDocumentTypesByPlan())) {
                                $fail('Tu plan actual no permite crear series para este tipo de comprobante.');
                                return;
                            }

                            if ($type === '01' && !str_starts_with($value, 'F')) {
                                $fail('La serie para Facturas debe empezar con F.');
                            }
                            if ($type === '03' && !str_starts_with($value, 'B')) {
                                $fail('La serie para Boletas debe empezar con B.');
                            }
                            if (in_array($type, ['07', '08']) && !in_array(substr($value, 0, 1), ['F', 'B'])) {
                                $fail('Las notas deben empezar con F (si es de factura) o B (si es de boleta).');
                            }
                            // 🌟 NUEVA REGLA PARA NOTAS DE VENTA
                            if ($type === '00' && !str_starts_with($value, 'N') && !str_starts_with($value, 'T')) {
                                $fail('La serie para Notas de Venta debe empezar con N o T (Ej: N001, T001).');
                            }
                        },
                    ])
                    ->columnSpan(1),

                Forms\Components\TextInput::make('correlative')
                    ->label('Último número emitido')
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->helperText('Pon 0 si es una serie nueva.')
                    ->columnSpan(1),

                Forms\Components\Toggle::make('active')
                    ->label('Serie activa')
                    ->default(true)
                    ->columnSpan(2),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

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
                    }),

                Tables\Columns\TextColumn::make('serie')
                    ->label('Serie')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('correlative')
                    ->label('Último correlativo')
                    ->sortable(),

                Tables\Columns\TextColumn::make('next_number')
                    ->label('Siguiente comprobante')
                    ->state(function ($record) {
                        $nextCorrelative = $record->correlative + 1;

                        return $record->serie . '-' . str_pad($nextCorrelative, 8, '0', STR_PAD_LEFT);
                    }),

                Tables\Columns\ToggleColumn::make('active')
                    ->label('Activa'),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil'),
                    Tables\Actions\DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation(),
                ])
                    ->label('Acciones')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->button()
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
