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
                        // PESTAÑA 1: Sistema SaaS (Tus campos originales intactos)
                        Forms\Components\Tabs\Tab::make('Sistema SaaS')
                            ->icon('heroicon-o-server')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre del Negocio')
                                    ->required()
                                    ->columnSpan(2),

                                // NUEVO: Selector de Giro de Negocio vinculado a la tabla business_sectors
                                Forms\Components\Select::make('business_sector_id')
                                    ->label('Giro del Negocio')
                                    ->relationship('businessSector', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('domain')
                                    ->label('Subdominio')
                                    ->unique(ignoreRecord: true)
                                    ->prefix('https://') // UX: Le muestra cómo se verá
                                    ->suffix('.tusaas.com') // 👈 CAMBIA ESTO por tu dominio real
                                    ->columnSpan(1),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('¿Está Activo?')
                                    ->helperText('Apágalo si el cliente no pagó su mensualidad.')
                                    ->default(true)
                                    ->columnSpanFull(), // Para que ocupe todo el ancho y no descuadre
                            ])->columns(2),

                        // PESTAÑA 2: Datos Fiscales (Para la factura)
                        Forms\Components\Tabs\Tab::make('Datos Fiscales')
                            ->icon('heroicon-o-building-storefront')
                            ->schema([
                                Forms\Components\TextInput::make('ruc')
                                    ->label('RUC')
                                    ->numeric()
                                    ->length(11) // Obliga a que sean 11 dígitos
                                    ->required() // El RUC debería ser obligatorio para SUNAT
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('business_name')
                                    ->label('Razón Social')
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('address')
                                    ->label('Dirección Fiscal')
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('ubigeo')
                                    ->label('Ubigeo')
                                    ->length(6)
                                    ->placeholder('Ej: 130101 (Trujillo)')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('email')
                                    ->label('Correo Electrónico')
                                    ->email()
                                    ->columnSpan(1),
                            ])->columns(3),

                        // PESTAÑA 3: Motor SUNAT (Credenciales)
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
                                    ->label('Usuario SOL'),
                                Forms\Components\TextInput::make('sunat_sol_pass')
                                    ->label('Clave SOL')
                                    ->password()
                                    ->revealable(),

                                Forms\Components\FileUpload::make('sunat_certificate')
                                    ->label('Certificado Digital (.pem / .pfx)')
                                    ->disk('sunat') // <-- OBLIGATORIO: Indica que use storage/app
                                    ->directory('certificates') // Se guardará en storage/app/private/certificates
                                    ->visibility('private')
                                    //->acceptedFileTypes(['application/x-x509-ca-cert', 'application/x-pkcs12', 'text/plain'])
                                    ->nullable()
                                    ->helperText('Puedes subir el certificado más adelante.'),

                                Forms\Components\TextInput::make('sunat_certificate_password')
                                    ->label('Contraseña del Certificado')
                                    ->password()
                                    ->revealable(),
                            ])->columns(2),

                        // PESTAÑA 4: Preferencias de Venta
                        Forms\Components\Tabs\Tab::make('Preferencias')
                            ->icon('heroicon-o-cog-8-tooth')
                            ->schema([
                                Forms\Components\TextInput::make('igv_percentage')
                                    ->label('Porcentaje de IGV (%)')
                                    ->numeric()
                                    ->default(18),

                                Forms\Components\Toggle::make('prices_include_igv')
                                    ->label('Los precios del catálogo ya incluyen IGV')
                                    ->default(true),

                                Forms\Components\Toggle::make('auto_send_sunat')
                                    ->label('Enviar a SUNAT automáticamente')
                                    ->default(true),
                            ])->columns(2),
                    ])
                    ->columnSpanFull()
                    // Evitamos que las pestañas se vean comprimidas en pantallas grandes
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
