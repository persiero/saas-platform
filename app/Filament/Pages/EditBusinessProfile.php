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
use Filament\Forms\Components\Textarea;

class EditBusinessProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Mi Empresa';

    protected static ?string $title = 'Mi Empresa';

    public function getSubheading(): ?string
    {
        return 'Configura los datos fiscales, tienda virtual e impuestos de tu negocio.';
    }

    protected static ?int $navigationSort = 6;

    protected static string $view = 'percy-core::filament.pages.edit-business-profile';

    public ?array $data = [];

    public bool $canViewSunatSettings = false;

    public bool $canViewOnlineStoreSettings = false;

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
        /** @var \Percy\Core\Models\User|null $user */
        $user = Auth::user();

        return $user !== null
            && $user->tenant_id !== null
            && $user->isAdmin();
    }

    public function mount(): void
    {
        /** @var \Percy\Core\Models\User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->tenant_id) {
            return;
        }

        $this->canViewSunatSettings = self::hasSunatAccess();
        $this->canViewOnlineStoreSettings = self::hasOnlineStoreAccess();

        $tenant = Tenant::find($user->tenant_id);

        if ($tenant) {
            $this->form->fill($tenant->toArray());
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Configuración')
                    ->persistTabInQueryString()
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
                                            ->imageEditor()
                                            ->imagePreviewHeight('120')
                                            ->disk('r2_public')
                                            ->directory('logos')
                                            ->visibility('public')
                                            ->maxSize(2048)
                                            ->helperText('Recomendado: imagen cuadrada o rectangular en PNG/JPG, máximo 2 MB.')
                                            ->columnSpanFull(),

                                        // 🌟 ENVOLVEMOS EL RESTO EN UN GRID ESTRICTO
                                        Grid::make([
                                            'default' => 1,
                                            'md' => 2,
                                        ])->schema([
                                            ColorPicker::make('primary_color')
                                                ->label('Color Principal de la Marca')
                                                ->default('#4f46e5')
                                                ->required()
                                                ->hintIcon(
                                                    'heroicon-m-question-mark-circle',
                                                    tooltip: 'Este color se usará como color principal en la tienda virtual.'
                                                ),

                                            // Le permitimos cambiar su nombre comercial visual (opcional, pero útil)
                                            TextInput::make('name')
                                                ->label('Nombre Comercial')
                                                ->placeholder('Ej: Bodega San Pedro')
                                                ->required()
                                                ->maxLength(150)
                                                ->hintIcon(
                                                    'heroicon-m-question-mark-circle',
                                                    tooltip: 'Este nombre se mostrará en el catálogo web y en el panel.'
                                                ),

                                            // 🌟 NUEVO: Campo de texto libre para el horario
                                            TextInput::make('business_hours')
                                                ->label('Horario de Atención')
                                                ->placeholder('Ej: Lun a Sáb de 8:00 AM a 10:00 PM')
                                                ->maxLength(120)
                                                ->columnSpanFull(),

                                            // 🌟 NUEVO: El interruptor maestro
                                            Toggle::make('is_open_for_orders')
                                                ->label('Tienda abierta para pedidos')
                                                ->helperText('Desactívalo temporalmente si no puedes atender pedidos online.')
                                                ->default(true)
                                                ->inline(false),

                                            // 🌟 NUEVO: COSTO DE DELIVERY Y YAPE
                                            TextInput::make('delivery_fee')
                                                ->label('Costo Base de Delivery')
                                                ->numeric()
                                                ->prefix('S/')
                                                ->minValue(0)
                                                ->step(0.10)
                                                ->default(0)
                                                ->helperText('Se sumará automáticamente cuando el cliente elija delivery.'),

                                            TextInput::make('yape_number')
                                                ->label('Número de Yape / Plin')
                                                ->tel()
                                                ->maxLength(20)
                                                ->placeholder('Ej: 999888777')
                                                ->helperText('Aparecerá como referencia de pago en el carrito.'),

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
                                        Grid::make([
                                            'default' => 1,
                                            'md' => 2,
                                        ])->schema([
                                            TextInput::make('ruc')
                                                ->label('RUC')
                                                ->numeric()
                                                ->length(11)
                                                ->required()
                                                ->placeholder('Ej: 20123456789'),

                                            TextInput::make('business_name')
                                                ->label('Razón Social')
                                                ->required()
                                                ->maxLength(200)
                                                ->placeholder('Ej: Mi Empresa S.A.C.'),

                                            Textarea::make('address')
                                                ->label('Dirección fiscal')
                                                ->rows(2)
                                                ->maxLength(255)
                                                ->columnSpanFull(),

                                            TextInput::make('ubigeo')
                                                ->label('Ubigeo')
                                                ->maxLength(6)
                                                ->placeholder('Ej: 130101'),

                                            TextInput::make('phone')
                                                ->label('Teléfono')
                                                ->tel()
                                                ->maxLength(20)
                                                ->placeholder('Ej: 987654321'),

                                            TextInput::make('email')
                                                ->email()
                                                ->label('Correo electrónico')
                                                ->maxLength(150)
                                                ->placeholder('Ej: ventas@miempresa.com'),
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
                                            ->label('Entorno de emisión')
                                            ->native(false)
                                            ->options([
                                                'beta' => 'Pruebas (BETA)',
                                                'production' => 'Producción',
                                            ])
                                            ->default('beta')
                                            ->required(),

                                        Placeholder::make('sunat_help')
                                            ->label('Importante')
                                            ->content('Verifica tus credenciales SOL y certificado antes de cambiar a producción.'),

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
                                            ->acceptedFileTypes([
                                                'application/x-pem-file',
                                                'application/x-pkcs12',
                                                'application/octet-stream',
                                                '.pem',
                                                '.pfx',
                                            ])
                                            ->helperText('Archivo privado usado para firmar XML. No será visible públicamente.')
                                            ->columnSpanFull(),

                                        TextInput::make('sunat_certificate_password')
                                            ->label('Contraseña del Certificado')
                                            ->password()
                                            ->revealable()
                                            ->helperText('Necesaria para firmar los XML.'),

                                    ])->columns([
                                        'default' => 1,
                                        'md' => 2,
                                    ])
                            ]),

                        // PESTAÑA 4: Preferencias (Tu código original intacto)
                        Tabs\Tab::make('Impuestos')
                            ->icon('heroicon-o-cog-8-tooth')
                            ->schema([
                                Section::make('Preferencias tributarias')
                                    ->description('Configura cómo se calcularán los impuestos en ventas, productos y comprobantes.')
                                    ->schema([
                                        Select::make('igv_percentage')
                                            ->label('Régimen Tributario - IGV (%)')
                                            ->options([
                                                '18.00' => '18% - Régimen General / MYPE Estándar',
                                                '10.50' => '10.5% - Ley MYPE Restaurantes y Hoteles',
                                                '0.00'  => '0% - Exonerado / Inafecto',
                                            ])
                                            ->native(false)
                                            ->default('18.00')
                                            ->required()
                                            ->hintIcon(
                                                'heroicon-m-question-mark-circle',
                                                tooltip: 'Asegúrate de cumplir los requisitos tributarios antes de usar una tasa reducida.'
                                            ),

                                        Toggle::make('prices_include_igv')
                                            ->label('Los precios del catálogo ya incluyen IGV')
                                            ->helperText('Si está activo, el sistema separará el IGV desde el precio final.')
                                            ->default(true)
                                            ->inline(false),

                                        Toggle::make('auto_send_sunat')
                                            ->label('Enviar a SUNAT automáticamente')
                                            ->helperText('Al emitir boletas o facturas, el sistema intentará enviarlas automáticamente.')
                                            ->default(true)
                                            ->inline(false)
                                            ->visible(fn(): bool => self::hasSunatAccess()),
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $tenant = Tenant::find(Auth::user()->tenant_id);

        if (! $tenant) {
            Notification::make()
                ->danger()
                ->title('No se encontró la empresa')
                ->body('No fue posible cargar la configuración del negocio.')
                ->send();

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
