<?php

namespace App\Filament\Resources\TenantResource\Schemas;

use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Builder;
use Percy\Core\Models\Plan;

class TenantForm
{
    public static function configure(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Configuración del Inquilino')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Sistema SaaS')
                            ->icon('heroicon-o-server')
                            ->schema([
                                Forms\Components\FileUpload::make('logo')
                                    ->label('Logo del Negocio')
                                    ->image()
                                    ->disk('r2_public')
                                    ->directory('logos')
                                    ->visibility('public')
                                    ->maxSize(2048)
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre del Negocio')
                                    ->required()
                                    ->maxLength(150)
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('business_sector_id')
                                    ->label('Giro del Negocio')
                                    ->relationship(
                                        'businessSector',
                                        'name',
                                        modifyQueryUsing: fn(Builder $query) => $query->where('is_active', true)
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(['default' => 1, 'sm' => 1]),

                                Forms\Components\Select::make('plan_id')
                                    ->label('Plan contratado')
                                    ->relationship(
                                        'plan',
                                        'name',
                                        modifyQueryUsing: fn(Builder $query) => $query
                                            ->where('is_active', true)
                                            ->orderBy('sort_order')
                                    )
                                    ->default(
                                        fn(): ?int => Plan::query()
                                            ->where('slug', 'premium')
                                            ->value('id')
                                    )
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Define qué módulos comerciales tendrá disponible este cliente.')
                                    ->columnSpan(['default' => 1, 'sm' => 1]),

                                Forms\Components\TextInput::make('domain')
                                    ->label('Subdominio')
                                    ->unique(ignoreRecord: true)
                                    ->prefix('https://')
                                    ->suffix('.virtualperu.online')
                                    ->maxLength(80)
                                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                    ->helperText('Usa solo minúsculas, números y guiones. Ejemplo: farmacia-san-jose.')
                                    ->dehydrateStateUsing(
                                        fn(?string $state): ?string =>
                                        filled($state) ? strtolower(trim($state)) : null
                                    )
                                    ->columnSpan(['default' => 1, 'sm' => 1]),

                                Forms\Components\ColorPicker::make('primary_color')
                                    ->label('Color de Marca')
                                    ->default('#4f46e5')
                                    ->required()
                                    ->columnSpan(['default' => 1, 'sm' => 1]),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('¿Está Activo?')
                                    ->helperText('Apágalo si el cliente no pagó su mensualidad.')
                                    ->default(true)
                                    ->columnSpan(['default' => 1, 'sm' => 1]),
                            ])
                            ->columns(['default' => 1, 'sm' => 2]),

                        Forms\Components\Tabs\Tab::make('Datos Fiscales')
                            ->icon('heroicon-o-building-storefront')
                            ->schema([
                                Forms\Components\TextInput::make('ruc')
                                    ->label('RUC')
                                    ->numeric()
                                    ->length(11)
                                    ->required()
                                    ->dehydrateStateUsing(
                                        fn(?string $state): ?string =>
                                        filled($state) ? preg_replace('/\D/', '', $state) : null
                                    )
                                    ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 1]),

                                Forms\Components\TextInput::make('business_name')
                                    ->label('Razón Social')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 1, 'sm' => 2, 'md' => 2]),

                                Forms\Components\TextInput::make('address')
                                    ->label('Dirección Fiscal')
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('ubigeo')
                                    ->label('Ubigeo')
                                    ->length(6)
                                    ->placeholder('Ej: 130101 (Trujillo)')
                                    ->columnSpan(['default' => 1, 'sm' => 1]),

                                Forms\Components\TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->maxLength(20)
                                    ->columnSpan(['default' => 1, 'sm' => 1]),

                                Forms\Components\TextInput::make('email')
                                    ->label('Correo Electrónico')
                                    ->email()
                                    ->dehydrateStateUsing(
                                        fn(?string $state): ?string =>
                                        filled($state) ? strtolower(trim($state)) : null
                                    )
                                    ->columnSpan(['default' => 1, 'sm' => 1]),
                            ])
                            ->columns(['default' => 1, 'sm' => 2, 'md' => 3]),

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
                                    ->maxLength(100)
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
                            ])
                            ->columns(['default' => 1, 'sm' => 2]),

                        Forms\Components\Tabs\Tab::make('Preferencias')
                            ->icon('heroicon-o-cog-8-tooth')
                            ->schema([
                                Forms\Components\Select::make('igv_percentage')
                                    ->label('Régimen Tributario - IGV (%)')
                                    ->options([
                                        '18.00' => '18% - Régimen General / MYPE Estándar',
                                        '10.50' => '10.5% - Ley MYPE Restaurantes y Hoteles',
                                        '0.00'  => '0% - Exonerado / Inafecto',
                                    ])
                                    ->default('18.00')
                                    ->required()
                                    ->helperText('Asegúrate de cumplir los requisitos de SUNAT si eliges el 10.5%.')
                                    ->columnSpanFull(),

                                Forms\Components\Toggle::make('prices_include_igv')
                                    ->label('Los precios del catálogo ya incluyen IGV')
                                    ->default(true)
                                    ->columnSpanFull(),

                                Forms\Components\Toggle::make('auto_send_sunat')
                                    ->label('Enviar a SUNAT automáticamente')
                                    ->default(true)
                                    ->columnSpanFull(),
                            ])
                            ->columns(['default' => 1, 'sm' => 2]),
                    ])
                    ->columnSpanFull()
                    ->contained(false),
            ]);
    }
}
