<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ColorPicker; // 🌟 IMPORTANTE: Agregamos el ColorPicker
use Filament\Notifications\Notification;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Models\Tenant;
use Percy\Core\Services\Tenants\TenantPlanService;

class EditBusinessProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $navigationLabel = 'Mi Empresa';
    protected static ?string $title = 'Mi Empresa';
    protected static ?int $navigationSort = 6;

    protected static string $view = 'percy-core::filament.pages.edit-business-profile';

    public ?array $data = [];

    private static function tenantPlanHas(string $feature): bool
    {
        /** @var \Percy\Core\Models\User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->tenant) {
            return false;
        }

        return app(TenantPlanService::class)->has($feature, $user->tenant);
    }

    private static function hasSunatAccess(): bool
    {
        return self::tenantPlanHas('has_sunat');
    }

    private static function hasOnlineStoreAccess(): bool
    {
        return self::tenantPlanHas('has_online_store')
            || self::tenantPlanHas('has_delivery');
    }

    public static function canAccess(): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = Auth::user();
        return Auth::check() && $user->tenant_id != null && $user->isAdmin();
    }

    public function mount(): void
    {
        $tenant = Tenant::find(Auth::user()->tenant_id);
        if ($tenant) {
            $this->form->fill($tenant->toArray());
        }
    }


    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Configuración')
                    ->tabs([
                        // 🌟 NUEVA PESTAÑA 1: MARCA Y CATÁLOGO (Exclusiva para el diseño de su web)
                        Tabs\Tab::make('Marca y Catálogo')
                            ->icon('heroicon-o-paint-brush')
                            ->visible(fn(): bool => self::hasOnlineStoreAccess())
                            ->schema([
                                Section::make('Personaliza tu Tienda Virtual')
                                    ->description('Sube tu logo y elige el color principal para que tu catálogo web tenga la identidad de tu negocio.')
                                    ->schema([
                                        FileUpload::make('logo')
                                            ->label('Logo del Negocio')
                                            ->image()
                                            ->disk('r2_public') // Guardamos en Cloudflare
                                            ->directory('logos')
                                            ->visibility('public')
                                            ->maxSize(2048)
                                            ->columnSpanFull(),

                                        // 🌟 ENVOLVEMOS EL RESTO EN UN GRID ESTRICTO
                                        Grid::make(2)->schema([
                                            ColorPicker::make('primary_color')
                                                ->label('Color Principal de la Marca')
                                                ->default('#4f46e5')
                                                ->required(),

                                            // Le permitimos cambiar su nombre comercial visual (opcional, pero útil)
                                            TextInput::make('name')
                                                ->label('Nombre Comercial (Se muestra en el catálogo)')
                                                ->required(),

                                            // 🌟 NUEVO: Campo de texto libre para el horario
                                            TextInput::make('business_hours')
                                                ->label('Horario de Atención (Visible para clientes)')
                                                ->placeholder('Ej: Lun a Sáb de 8:00 AM a 10:00 PM'),

                                            // 🌟 NUEVO: El interruptor maestro
                                            Toggle::make('is_open_for_orders')
                                                ->label('Estado de la Tienda (Abierto para recibir pedidos)')
                                                ->helperText('Apágalo si cierras temprano o no puedes atender temporalmente.')
                                                ->default(true)
                                                ->inline(false), // 🌟 ESTA ES LA CLAVE MAGICA

                                            // 🌟 NUEVO: COSTO DE DELIVERY Y YAPE
                                            TextInput::make('delivery_fee')
                                                ->label('Costo Base de Delivery')
                                                ->numeric()
                                                ->prefix('S/')
                                                ->default(0)
                                                ->helperText('Se sumará automáticamente si piden Delivery.'),

                                            TextInput::make('yape_number')
                                                ->label('Número de Yape / Plin')
                                                ->tel()
                                                ->placeholder('Ej: 999888777')
                                                ->helperText('Aparecerá en el carrito de compras.'),

                                        ]),
                                    ]),
                            ]),

                        // PESTAÑA 2: Datos Fiscales (Tu código original intacto)
                        Tabs\Tab::make('Datos Fiscales')
                            ->icon('heroicon-o-building-storefront')
                            ->schema([
                                Section::make('Información fiscal')
                                    ->description('Datos registrados en SUNAT')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('ruc')
                                                ->label('RUC')
                                                ->numeric()
                                                ->maxLength(11)
                                                ->required(),
                                            TextInput::make('business_name')
                                                ->label('Razón Social')
                                                ->required(),
                                            TextInput::make('address')
                                                ->label('Dirección fiscal'),
                                            TextInput::make('ubigeo')
                                                ->label('Ubigeo'),
                                            TextInput::make('phone')
                                                ->label('Teléfono'),
                                            TextInput::make('email')
                                                ->email()
                                                ->label('Correo electrónico'),
                                        ])
                                    ])
                            ]),

                        // PESTAÑA 3: Motor SUNAT (Tu código original intacto)
                        Tabs\Tab::make('Facturación Electrónica')
                            ->icon('heroicon-o-document-check')
                            ->visible(fn(): bool => $this->hasSunatAccess())
                            ->schema([
                                Section::make('Configuración SUNAT')
                                    ->description('Credenciales necesarias para emitir comprobantes electrónicos.')
                                    ->schema([
                                        Select::make('sunat_environment')
                                            ->label('Entorno')
                                            ->options([
                                                'beta' => 'Pruebas (BETA)',
                                                'production' => 'Producción',
                                            ])
                                            ->default('beta')
                                            ->required(),
                                        Placeholder::make(''),
                                        TextInput::make('sunat_sol_user')
                                            ->label('Usuario SOL')
                                            ->helperText('Ingresa solo tu usuario (Ej: MODDATOS).'),
                                        TextInput::make('sunat_sol_pass')
                                            ->label('Clave SOL')
                                            ->password()
                                            ->revealable(),
                                        FileUpload::make('sunat_certificate')
                                            ->label('Certificado Digital (.pem / .pfx)')
                                            ->directory('certificates')
                                            ->disk('sunat')
                                            ->visibility('private')
                                            ->helperText('Sube el archivo proporcionado por tu entidad certificadora.'),
                                        TextInput::make('sunat_certificate_password')
                                            ->label('Contraseña del Certificado')
                                            ->password()
                                            ->revealable()
                                            ->helperText('Necesaria para firmar los XML.'),
                                    ])->columns(2)
                            ]),

                        // PESTAÑA 4: Preferencias (Tu código original intacto)
                        Tabs\Tab::make('Impuestos')
                            ->icon('heroicon-o-cog-8-tooth')
                            ->schema([
                                Select::make('igv_percentage')
                                    ->label('Régimen Tributario - IGV (%)')
                                    ->options([
                                        '18.00' => '18% - Régimen General / MYPE Estándar',
                                        '10.50' => '10.5% - Ley MYPE Restaurantes y Hoteles',
                                        '0.00'  => '0% - Exonerado / Inafecto (Amazonía, etc.)',
                                    ])
                                    ->default('18.00')
                                    ->required()
                                    ->helperText('Asegúrate de cumplir los requisitos de SUNAT si eliges el 10.5%.'),
                                Toggle::make('prices_include_igv')
                                    ->label('Los precios del catálogo ya incluyen IGV')
                                    ->default(true),
                                Toggle::make('auto_send_sunat')
                                    ->label('Enviar a SUNAT automáticamente')
                                    ->default(true)
                                    ->visible(fn(): bool => $this->hasSunatAccess()),
                            ])->columns(2),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $tenant = Tenant::find(Auth::user()->tenant_id);

        if (! $tenant) {
            return;
        }

        $data = $this->form->getState();

        if (! self::hasOnlineStoreAccess()) {
            unset(
                $data['logo'],
                $data['primary_color'],
                $data['business_hours'],
                $data['is_open_for_orders'],
                $data['delivery_fee'],
                $data['yape_number']
            );
        }

        if (! self::hasSunatAccess()) {
            unset(
                $data['sunat_environment'],
                $data['sunat_sol_user'],
                $data['sunat_sol_pass'],
                $data['sunat_certificate'],
                $data['sunat_certificate_password'],
                $data['auto_send_sunat']
            );
        }

        $tenant->update($data);

        Notification::make()
            ->success()
            ->title('Configuración guardada exitosamente')
            ->send();
    }
}
