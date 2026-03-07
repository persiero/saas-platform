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
                                TextInput::make('ruc')->label('RUC')->numeric()->maxLength(11),
                                TextInput::make('business_name')->label('Razón Social'),
                                TextInput::make('address')->label('Dirección Fiscal'),
                                TextInput::make('ubigeo')->label('Ubigeo (Ej: 130105)')->maxLength(6),
                                TextInput::make('phone')->label('Teléfono'),
                                TextInput::make('email')->label('Correo Electrónico')->email(),
                            ])->columns(2),

                        // PESTAÑA 2: Motor SUNAT
                        Tabs\Tab::make('Facturación Electrónica')
                            ->icon('heroicon-o-document-check')
                            ->schema([
                                Select::make('sunat_environment')
                                    ->label('Entorno SUNAT')
                                    ->options([
                                        'beta' => 'Entorno de Pruebas (BETA)',
                                        'production' => 'Producción (OFICIAL)',
                                    ])
                                    ->default('beta')
                                    ->required()
                                    ->columnSpanFull(),

                                TextInput::make('sunat_sol_user')
                                    ->label('Usuario SOL')
                                    // Le damos instrucciones claras al cliente
                                    ->helperText('Ingresa solo tu usuario (Ej: MODDATOS). El sistema agregará tu RUC automáticamente.'),
                                TextInput::make('sunat_sol_pass')
                                    ->label('Clave SOL')
                                    ->password()
                                    ->revealable(),

                                FileUpload::make('sunat_certificate')
                                    ->label('Certificado Digital (.pem)')
                                    ->directory('certificates')
                                    // Quitamos la restricción estricta de MIME Types que hace fallar a Windows
                                    // Y le avisamos al usuario que puede seleccionar "Todos los archivos"
                                    ->helperText('Sube tu archivo .pem. Si no lo ves en la ventana, cambia a "Todos los archivos".'),
                            ])->columns(2),

                        // PESTAÑA 3: Preferencias
                        Tabs\Tab::make('Preferencias')
                            ->icon('heroicon-o-cog-8-tooth')
                            ->schema([
                                TextInput::make('igv_percentage')
                                    ->label('Porcentaje de IGV (%)')
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
