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

class EditBusinessProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $navigationLabel = 'Mi Empresa';
    protected static ?string $title = 'Configuración de SUNAT';
    protected static ?int $navigationSort = 61;

    protected static string $view = 'percy-core::filament.pages.edit-business-profile';

    public ?array $data = [];

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
                                TextInput::make('igv_percentage')
                                    ->label('IGV (%)')
                                    ->numeric()
                                    ->default(18),
                                Toggle::make('prices_include_igv')
                                    ->label('Los precios del catálogo ya incluyen IGV')
                                    ->default(true),
                                Toggle::make('auto_send_sunat')
                                    ->label('Enviar a SUNAT automáticamente')
                                    ->default(true),
                            ])->columns(2),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $tenant = Tenant::find(Auth::user()->tenant_id);
        if ($tenant) {
            $tenant->update($this->form->getState());

            Notification::make()
                ->success()
                ->title('Configuración guardada exitosamente')
                ->send();
        }
    }
}
