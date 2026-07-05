<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use Percy\Core\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?string $modelLabel = 'Cliente';
    protected static ?string $pluralModelLabel = 'Clientes';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id) // 1. Filtro SaaS
            // ❌ Quitamos el ->with(['category']) porque estos módulos no lo necesitan
            ->withoutGlobalScopes([
                SoftDeletingScope::class, // 2. Permite ver la papelera
            ]);
    }

    // 🌟 MAGIA SAAS: Ocultar este menú a los Mozos (Vendedores)
    public static function canViewAny(): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();

        // Si el usuario NO tiene el rol de Vendedor, puede ver el menú
        return !$user->hasRole('Vendedor');
    }

    public static function canCreate(): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        return $user->isAdmin();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        return $user->isAdmin();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        return $user->isAdmin();
    }

    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        return $user->isAdmin(); // Solo el Admin puede restaurar
    }

    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    // 5. Restricción general para Bulk Actions (Aplica para eliminar/restaurar masivamente)
    public static function canDeleteAny(): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        return $user->isAdmin();
    }

    public static function canRestoreAny(): bool
    {
        /** @var \Percy\Core\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        return $user->isAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identidad del Cliente')
                    ->description('Datos principales para boletas, facturas y ventas.')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\Select::make('document_type')
                            ->label('Tipo de Documento')
                            ->options([
                                'DNI' => 'DNI',
                                'RUC' => 'RUC',
                                'CE' => 'Carné de Extranjería',
                            ])
                            ->default('DNI')
                            ->required()
                            ->native(false)
                            ->live() // 🌟 ¡ESTO ES VITAL PARA QUE APAREZCA LA LUPA AL CAMBIAR A RUC
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('document_number')
                            ->label('Número')
                            // 🌟 Validación Dinámica: Longitud máxima
                            ->maxLength(fn(\Filament\Forms\Get $get) => match ($get('document_type')) {
                                'DNI' => 8,
                                'RUC' => 11,
                                default => 15,
                            })
                            // 🌟 Validación Dinámica: Longitud mínima estricta
                            ->minLength(fn(\Filament\Forms\Get $get) => match ($get('document_type')) {
                                'DNI' => 8,
                                'RUC' => 11,
                                default => null,
                            })
                            // 🌟 Forzar teclado numérico
                            ->numeric(fn(\Filament\Forms\Get $get) => in_array($get('document_type'), ['DNI', 'RUC']))
                            ->placeholder(fn(\Filament\Forms\Get $get) => $get('document_type') === 'RUC' ? 'Ej: 20... (11 dígitos)' : 'Ej: 12345678')
                            ->required()
                            ->columnSpan(1)
                            // 🌟 MAGIA: Botón de Decolecta (Solo visible en RUC)
                            ->suffixAction(
                                \Filament\Forms\Components\Actions\Action::make('searchDecolecta')
                                    ->icon('heroicon-m-magnifying-glass')
                                    ->color('primary')
                                    ->tooltip('Buscar RUC (Decolecta)')
                                    ->visible(fn(\Filament\Forms\Get $get) => $get('document_type') === 'RUC')
                                    ->action(function ($state, \Filament\Forms\Set $set) {
                                        if (blank($state) || strlen($state) !== 11) {
                                            \Filament\Notifications\Notification::make()
                                                ->danger()
                                                ->title('Error')
                                                ->body('Ingrese un RUC válido de 11 dígitos.')
                                                ->send();
                                            return;
                                        }

                                        // 🌟 Llamamos al config() como unos verdaderos pros
                                        $token = config('services.decolecta.token');

                                        try {
                                            $response = \Illuminate\Support\Facades\Http::withToken($token)
                                                ->timeout(10)
                                                ->get("https://api.decolecta.com/v1/sunat/ruc?numero={$state}");

                                            if ($response->successful()) {
                                                $data = $response->json();

                                                if (($data['estado'] ?? '') !== 'ACTIVO') {
                                                    \Filament\Notifications\Notification::make()
                                                        ->warning()
                                                        ->title('Cuidado')
                                                        ->body('Este RUC figura como ' . ($data['estado'] ?? 'INACTIVO') . ' en SUNAT.')
                                                        ->send();
                                                } else {
                                                    \Filament\Notifications\Notification::make()->success()->title('RUC Encontrado')->send();
                                                }

                                                $set('name', $data['razon_social'] ?? '');

                                                // Construcción de la dirección limpia
                                                $dir = trim($data['direccion'] ?? '');
                                                $dep = trim($data['departamento'] ?? '');
                                                $prov = trim($data['provincia'] ?? '');
                                                $dist = trim($data['distrito'] ?? '');

                                                $fullAddress = trim("$dir $dep - $prov - $dist", " -");
                                                $fullAddress = preg_replace('/\s+/', ' ', $fullAddress);

                                                $set('address', $fullAddress);
                                            } else {
                                                \Filament\Notifications\Notification::make()
                                                    ->danger()
                                                    ->title('No encontrado')
                                                    ->body('El RUC no existe en SUNAT o superó el límite.')
                                                    ->send();
                                            }
                                        } catch (\Exception $e) {
                                            \Filament\Notifications\Notification::make()
                                                ->danger()
                                                ->title('Error de conexión')
                                                ->body('No se pudo conectar con la API de Decolecta.')
                                                ->send();
                                        }
                                    })
                            ),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre Completo o Razón Social')
                            ->required()
                            ->maxLength(150)
                            ->placeholder('Ej: Juan Pérez o Empresa SAC')
                            ->columnSpanFull(),
                    ])->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),

                Forms\Components\Section::make('Datos de Contacto')
                    ->description('Opcional: Medios para envío de comprobantes')
                    ->icon('heroicon-o-envelope')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(30)
                            ->placeholder('Ej: 987654321')
                            ->prefixIcon('heroicon-o-phone')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->maxLength(150)
                            ->placeholder('Ej: cliente@ejemplo.com')
                            ->prefixIcon('heroicon-o-envelope')
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('address')
                            ->label('Dirección Fija')
                            ->maxLength(255)
                            ->rows(2)
                            ->placeholder('Ej: Av. Principal 123, Trujillo')
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->collapsible(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Detalle del Cliente')
                    ->icon('heroicon-o-user')
                    ->description('Información registrada para ventas, boletas y facturas.')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Cliente')
                            ->weight('black')
                            ->size(TextEntry\TextEntrySize::Large)
                            ->icon(fn(Customer $record): string => match ($record->document_type) {
                                'RUC', '6' => 'heroicon-o-building-office-2',
                                default => 'heroicon-o-user',
                            })
                            ->columnSpanFull(),

                        TextEntry::make('document_type')
                            ->label('Tipo de documento')
                            ->badge()
                            ->formatStateUsing(fn(?string $state): string => match ($state) {
                                '1' => 'DNI',
                                '6' => 'RUC',
                                '4' => 'CE',
                                default => $state ?? 'No registrado',
                            })
                            ->color(fn(?string $state): string => match ($state) {
                                'RUC', '6' => 'info',
                                'DNI', '1' => 'gray',
                                'CE', '4' => 'warning',
                                default => 'gray',
                            }),

                        TextEntry::make('document_number')
                            ->label('Número de documento')
                            ->copyable()
                            ->copyMessage('Documento copiado')
                            ->placeholder('No registrado'),

                        TextEntry::make('phone')
                            ->label('Teléfono')
                            ->icon('heroicon-o-phone')
                            ->copyable()
                            ->copyMessage('Teléfono copiado')
                            ->placeholder('Sin teléfono'),

                        TextEntry::make('email')
                            ->label('Correo electrónico')
                            ->icon('heroicon-o-envelope')
                            ->copyable()
                            ->copyMessage('Correo copiado')
                            ->placeholder('Sin correo'),

                        TextEntry::make('address')
                            ->label('Dirección')
                            ->icon('heroicon-o-map-pin')
                            ->placeholder('Sin dirección')
                            ->columnSpanFull(),

                        TextEntry::make('created_at')
                            ->label('Registrado')
                            ->dateTime('d/m/Y h:i A')
                            ->icon('heroicon-o-calendar'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->columns([
                Tables\Columns\TextColumn::make('mobile_summary')
                    ->label('Cliente')
                    ->state(fn(Customer $record): string => $record->name)
                    ->description(function (Customer $record): string {
                        $tipo = match ($record->document_type) {
                            '1' => 'DNI',
                            '6' => 'RUC',
                            '4' => 'CE',
                            default => $record->document_type ?? 'Documento',
                        };

                        $documento = $record->document_number ?: 'Sin número';
                        $telefono = $record->phone ?: 'Sin teléfono';

                        return "{$tipo}: {$documento} · {$telefono}";
                    })
                    ->icon(fn(Customer $record): string => match ($record->document_type) {
                        'RUC', '6' => 'heroicon-o-building-office-2',
                        default => 'heroicon-o-user',
                    })
                    ->weight('black')
                    ->wrap()
                    ->searchable(['name', 'document_number', 'phone', 'email'])
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    // 🌟 MAGIA UI: Limita a 40 caracteres y pone "..."
                    ->limit(40)
                    // 🌟 MAGIA UX: Muestra el nombre completo al pasar el mouse
                    ->tooltip(fn(Customer $record): string => $record->name)
                    ->icon(fn(Customer $record): string => match ($record->document_type) {
                        'RUC', '6' => 'heroicon-o-building-office-2', // Icono de edificio para empresas
                        default => 'heroicon-o-user', // Icono de persona para DNI, CE, etc.
                    })
                    ->description(fn(Customer $record): ?string => $record->email)
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('document_type')
                    ->label('Documento')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        '1' => 'DNI',
                        '6' => 'RUC',
                        '4' => 'CE',
                        default => $state,
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'RUC', '6'  => 'info',
                        'DNI', '1'  => 'gray',
                        'CE', '4' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn(Customer $record): ?string => $record->document_number)
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon('heroicon-o-phone')
                    ->searchable()
                    ->placeholder('Sin teléfono')
                    ->toggleable()
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('address')
                    ->label('Dirección')
                    ->limit(40)
                    ->icon('heroicon-o-map-pin')
                    ->searchable()
                    ->placeholder('Sin dirección')
                    ->tooltip(fn(Customer $record): ?string => $record->address)
                    ->toggleable()
                    ->visibleFrom('xl'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->since()
                    ->icon('heroicon-o-calendar')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Tipo de Documento')
                    ->options([
                        'DNI' => 'DNI',
                        'RUC' => 'RUC',
                        'CE' => 'Carné de Extranjería',
                        'PASAPORTE' => 'Pasaporte',
                    ])
                    ->multiple(),

                TrashedFilter::make(),
            ])
            ->filtersFormColumns([
                'default' => 1,
                'md' => 2,
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Ver detalles')
                        ->icon('heroicon-o-eye')
                        ->color('info'),

                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil')
                        ->color('warning'),

                    Tables\Actions\DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Cliente')
                        ->modalDescription('¿Estás seguro de que deseas eliminar este cliente? Esta acción no se puede deshacer.'),

                    Tables\Actions\RestoreAction::make()
                        ->label('Restaurar')
                        ->icon('heroicon-o-arrow-uturn-left') // Icono de "Deshacer"
                        ->color('success') // Color verde positivo
                        ->requiresConfirmation()
                        ->modalHeading('Restaurar Cliente')
                        ->modalDescription('¿Deseas rescatar este cliente de la papelera? Volverá a estar visible y activo en el sistema.'),

                ])
                    ->label('Acciones')
                    ->tooltip('Acciones')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
                    ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Clientes')
                        ->modalDescription('¿Estás seguro de que deseas eliminar los clientes seleccionados?'),
                    Tables\Actions\RestoreBulkAction::make(), // 🌟 Restaurar varios a la vez
                ]),
            ])
            ->emptyStateHeading('Sin clientes registrados')
            ->emptyStateDescription('Comienza agregando tu primer cliente')
            ->emptyStateIcon('heroicon-o-users');
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
