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
                                    ->required(),
                                    
                                Forms\Components\TextInput::make('domain')
                                    ->label('Dominio (Subdominio)')
                                    ->unique(ignoreRecord: true),
                                    
                                Forms\Components\TextInput::make('database_name')
                                    ->label('Base de Datos Asignada')
                                    ->required(),
                                    
                                Forms\Components\Toggle::make('is_active')
                                    ->label('¿Está Activo?')
                                    ->default(true),
                            ])->columns(2),

                        // PESTAÑA 2: Datos Fiscales (Para la factura)
                        Forms\Components\Tabs\Tab::make('Datos Fiscales')
                            ->icon('heroicon-o-building-storefront')
                            ->schema([
                                Forms\Components\TextInput::make('ruc')
                                    ->label('RUC')
                                    ->numeric()
                                    ->maxLength(11),
                                Forms\Components\TextInput::make('business_name')
                                    ->label('Razón Social'),
                                Forms\Components\TextInput::make('address')
                                    ->label('Dirección Fiscal'),
                                Forms\Components\TextInput::make('ubigeo')
                                    ->label('Ubigeo (Ej: 130105)')
                                    ->maxLength(6),
                                Forms\Components\TextInput::make('phone')
                                    ->label('Teléfono'),
                                Forms\Components\TextInput::make('email')
                                    ->label('Correo Electrónico')
                                    ->email(),
                            ])->columns(2),

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
                                    ->directory('certificates') 
                                    ->acceptedFileTypes(['application/x-x509-ca-cert', 'application/x-pkcs12', 'text/plain']),
                                    
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
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
