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

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Clientes';
    protected static ?string $modelLabel = 'Cliente';
    protected static ?string $pluralModelLabel = 'Clientes';
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?int $navigationSort = 20;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id) // 1. Filtro SaaS
            // ❌ Quitamos el ->with(['category']) porque estos módulos no lo necesitan
            ->withoutGlobalScopes([
                SoftDeletingScope::class, // 2. Permite ver la papelera
            ]);
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
                    ->description('Datos personales o comerciales')
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
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('document_number')
                            ->label('Número')
                            ->maxLength(20)
                            ->placeholder('Ej: 12345678')
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre Completo o Razón Social')
                            ->required()
                            ->maxLength(150)
                            ->placeholder('Ej: Juan Pérez o Empresa SAC')
                            ->columnSpan(2),
                    ])->columns(2),

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
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon(fn (Customer $record): string => match ($record->document_type) {
                        'RUC', '6' => 'heroicon-o-building-office-2', // Icono de edificio para empresas
                        default => 'heroicon-o-user', // Icono de persona para DNI, CE, etc.
                    })
                    ->description(fn (Customer $record): ?string => $record->email),

                Tables\Columns\TextColumn::make('document_type')
                    ->label('Documento')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        '1' => 'DNI',
                        '6' => 'RUC',
                        '4' => 'CE',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'RUC', '6'  => 'info',
                        'DNI', '1'  => 'gray',
                        'CE', '4' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (Customer $record): ?string => $record->document_number),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon('heroicon-o-phone')
                    ->searchable()
                    ->placeholder('Sin teléfono')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('address')
                    ->label('Dirección')
                    ->limit(40)
                    ->icon('heroicon-o-map-pin')
                    ->searchable()
                    ->placeholder('Sin dirección')
                    ->tooltip(fn (Customer $record): ?string => $record->address)
                    ->toggleable(),

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
                ->icon('heroicon-o-ellipsis-vertical')
                ->button()
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
