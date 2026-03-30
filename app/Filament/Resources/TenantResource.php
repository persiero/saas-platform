<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Filament\Resources\TenantResource\RelationManagers;
use Percy\Core\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    /**
     * ¿Quién puede ver este menú en la barra lateral?
     * Solo el "Dueño del SaaS" (Aquel que NO pertenece a ningún negocio: tenant_id = null)
     */
    public static function canViewAny(): bool
    {
        return Auth::check() && Auth::user()->tenant_id === null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Configuración del Inquilino')
                    ->tabs([
                        // PESTAÑA 1: Sistema SaaS
                        Forms\Components\Tabs\Tab::make('Sistema SaaS')
                            ->icon('heroicon-o-server')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre del Negocio')
                                    ->required()
                                    ->columnSpanFull(), // 🌟 Cambiado a Full para que siempre ocupe todo el ancho

                                Forms\Components\Select::make('business_sector_id')
                                    ->label('Giro del Negocio')
                                    ->relationship('businessSector', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(['default' => 1, 'sm' => 1]), // 🌟 1 col en móvil, 1 en PC

                                Forms\Components\TextInput::make('domain')
                                    ->label('Subdominio')
                                    ->unique(ignoreRecord: true)
                                    ->prefix('https://')
                                    ->suffix('.tusaas.com')
                                    ->columnSpan(['default' => 1, 'sm' => 1]), // 🌟 1 col en móvil, 1 en PC

                                Forms\Components\Toggle::make('is_active')
                                    ->label('¿Está Activo?')
                                    ->helperText('Apágalo si el cliente no pagó su mensualidad.')
                                    ->default(true)
                                    ->columnSpanFull(),
                            ])->columns(['default' => 1, 'sm' => 2]), // 🌟 MAGIA RESPONSIVE: 1 col móvil, 2 col PC

                        // PESTAÑA 2: Datos Fiscales
                        Forms\Components\Tabs\Tab::make('Datos Fiscales')
                            ->icon('heroicon-o-building-storefront')
                            ->schema([
                                Forms\Components\TextInput::make('ruc')
                                    ->label('RUC')
                                    ->numeric()
                                    ->length(11)
                                    ->required()
                                    ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 1]),

                                Forms\Components\TextInput::make('business_name')
                                    ->label('Razón Social')
                                    ->required()
                                    ->columnSpan(['default' => 1, 'sm' => 2, 'md' => 2]), // Ocupa más espacio en PC

                                Forms\Components\TextInput::make('address')
                                    ->label('Dirección Fiscal')
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('ubigeo')
                                    ->label('Ubigeo')
                                    ->length(6)
                                    ->placeholder('Ej: 130101 (Trujillo)')
                                    ->columnSpan(['default' => 1, 'sm' => 1]),

                                Forms\Components\TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->columnSpan(['default' => 1, 'sm' => 1]),

                                Forms\Components\TextInput::make('email')
                                    ->label('Correo Electrónico')
                                    ->email()
                                    ->columnSpan(['default' => 1, 'sm' => 1]),
                            ])->columns(['default' => 1, 'sm' => 2, 'md' => 3]), // 🌟 1 en móvil, 2 en tablet, 3 en monitor grande

                        // PESTAÑA 3: Motor SUNAT
                        Forms\Components\Tabs\Tab::make('Facturación Electrónica')
                            ->icon('heroicon-o-document-check')
                            ->schema([
                                Forms\Components\Select::make('sunat_environment')
                                    ->label('Entorno SUNAT')
                                    ->options([
                                        'beta' => 'Entorno de Pruebas (BETA)',
                                        'production' => 'Producción (OFICIAL)',
                                    ])
                                    ->default('beta')
                                    ->required()
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('sunat_sol_user')
                                    ->label('Usuario SOL')
                                    ->columnSpan(['default' => 1, 'sm' => 1]),

                                Forms\Components\TextInput::make('sunat_sol_pass')
                                    ->label('Clave SOL')
                                    ->password()
                                    ->revealable()
                                    ->columnSpan(['default' => 1, 'sm' => 1]),

                                Forms\Components\FileUpload::make('sunat_certificate')
                                    ->label('Certificado Digital (.pem / .pfx)')
                                    ->disk('sunat')
                                    ->directory('certificates')
                                    ->visibility('private')
                                    ->nullable()
                                    ->helperText('Puedes subir el certificado más adelante.')
                                    ->columnSpan(['default' => 1, 'sm' => 1]),

                                Forms\Components\TextInput::make('sunat_certificate_password')
                                    ->label('Contraseña del Certificado')
                                    ->password()
                                    ->revealable()
                                    ->columnSpan(['default' => 1, 'sm' => 1]),
                            ])->columns(['default' => 1, 'sm' => 2]), // 🌟 1 col móvil, 2 col PC

                        // PESTAÑA 4: Preferencias de Venta
                        Forms\Components\Tabs\Tab::make('Preferencias')
                            ->icon('heroicon-o-cog-8-tooth')
                            ->schema([
                                Forms\Components\TextInput::make('igv_percentage')
                                    ->label('Porcentaje de IGV (%)')
                                    ->numeric()
                                    ->default(18)
                                    ->columnSpanFull(), // Mejor full para que no quede huérfano

                                Forms\Components\Toggle::make('prices_include_igv')
                                    ->label('Los precios del catálogo ya incluyen IGV')
                                    ->default(true)
                                    ->columnSpanFull(),

                                Forms\Components\Toggle::make('auto_send_sunat')
                                    ->label('Enviar a SUNAT automáticamente')
                                    ->default(true)
                                    ->columnSpanFull(),
                            ])->columns(['default' => 1, 'sm' => 2]),
                    ])
                    ->columnSpanFull()
                    ->contained(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Negocio')
                    ->searchable()
                    ->weight('bold')
                    ->icon('heroicon-o-building-storefront')
                    ->description(fn (Tenant $record): ?string => $record->business_name), // Razón social debajo

                // Muestra el nombre del sector (Ej: Restaurante, Farmacia)
                Tables\Columns\TextColumn::make('businessSector.name')
                    ->label('Giro')
                    ->badge() // Lo muestra como una etiqueta de color
                    ->color('info'),

                Tables\Columns\TextColumn::make('ruc')
                    ->label('RUC')
                    ->searchable()
                    ->copyable(), // UX: Copia rápida para consultar en SUNAT

                // Indicador visual de si ya subieron certificado
                Tables\Columns\IconColumn::make('sunat_certificate')
                    ->label('Cert. SUNAT')
                    ->boolean()
                    ->state(fn (Tenant $record): bool => !empty($record->sunat_certificate))
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Acceso')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado de Acceso')
                    ->trueLabel('Activos')
                    ->falseLabel('Suspendidos'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Configurar')
                    ->icon('heroicon-o-cog-6-tooth'),
            ])
            ->emptyStateHeading('Aún no tienes clientes')
            ->emptyStateDescription('Registra tu primer cliente SaaS para empezar.')
            ->emptyStateIcon('heroicon-o-server-stack')

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
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
