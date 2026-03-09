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
use Filament\Notifications\Notification;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Models\Tenant; // Tu modelo de inquilinos

class EditBusinessProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $navigationLabel = 'Mi Empresa';
    protected static ?string $title = 'Configuración de SUNAT';
    protected static ?int $navigationSort = 61;

    // Aquí le decimos a Filament qué vista HTML va a pintar
    protected static string $view = 'percy-core::filament.pages.edit-business-profile';

    public ?array $data = [];

    // MAGIA DE SEGURIDAD: Solo los inquilinos pueden ver esta página (el Superadmin no)
    public static function canAccess(): bool
    {
        // Usamos != en lugar de !== por si acaso Laravel lo está leyendo como texto "1" en vez de entero
        return Auth::check() && Auth::user()->tenant_id != null;
    }

    public function mount(): void
    {
        // Buscamos la empresa a la que pertenece el usuario logueado
        $tenant = Tenant::find(Auth::user()->tenant_id);
        if ($tenant) {
            // Llenamos los campos con los datos de la BD
            $this->form->fill($tenant->toArray());
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Configuración')
                    ->tabs([
                        // PESTAÑA 1: Datos Fiscales
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

                        // PESTAÑA 2: Motor SUNAT
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

                                    // Campo vacío para alinear el grid
                                    Placeholder::make(''),

                                    TextInput::make('sunat_sol_user')
                                        ->label('Usuario SOL')
                                        // Le damos instrucciones claras al cliente
                                        ->helperText('Ingresa solo tu usuario (Ej: MODDATOS).'),

                                    TextInput::make('sunat_sol_pass')
                                        ->label('Clave SOL')
                                        ->password()
                                        ->revealable(),

                                    FileUpload::make('sunat_certificate')
                                        ->label('Certificado Digital (.pem / .pfx)')
                                        ->directory('certificates')
                                        // Es vital mantener el disco correcto para la seguridad
                                        ->disk('sunat')
                                        ->visibility('private')
                                        ->helperText('Sube el archivo proporcionado por tu entidad certificadora.'),

                                    // ¡AQUÍ FALTABA ESTE CAMPO!
                                    TextInput::make('sunat_certificate_password')
                                        ->label('Contraseña del Certificado')
                                        ->password()
                                        ->revealable()
                                        ->helperText('Necesaria para firmar los XML.'),

                            ])->columns(2)

                        ]),


                        // PESTAÑA 3: Preferencias
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
            // Actualizamos la base de datos con los cambios del formulario
            $tenant->update($this->form->getState());

            // Mostramos una alerta verde bonita
            Notification::make()
                ->success()
                ->title('Configuración guardada exitosamente')
                ->send();
        }
    }

}
