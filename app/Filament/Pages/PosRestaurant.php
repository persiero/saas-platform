<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Percy\Core\Models\Zone;
use Illuminate\Support\Facades\Auth;
use App\Filament\Pages\PosOrder;

class PosRestaurant extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Atención de Mesas';
    protected static ?string $title = 'Monitor de Mesas';
    protected static string $view = 'filament.pages.pos-restaurant';
    protected static ?int $navigationSort = 1; // 👈 Va primero


    // 🌟 MAGIA SAAS: Solo visible para restaurantes
    public static function canAccess(): bool
    {
        $features = Auth::user()->tenant->businessSector->features ?? [];
        return $features['has_tables'] ?? false;
    }

    // 🌟 Enviamos los datos a la vista
    protected function getViewData(): array
    {
        $zones = Zone::where('tenant_id', Auth::user()->tenant_id)
            ->where('is_active', true)
            ->with(['tables' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get();

        return [
            'zones' => $zones,
        ];
    }

    // 🌟 La acción que ocurrirá al tocar una mesa
    public function openTable($tableId)
    {
        $table = \Percy\Core\Models\Table::where('tenant_id', Auth::user()->tenant_id)->findOrFail($tableId);

        // 1. Buscamos si la mesa YA TIENE una cuenta abierta (pending)
        $sale = \Percy\Core\Models\Sale::where('table_id', $table->id)
            ->where('status', 'pending')
            ->first();

        // 2. Si NO hay cuenta abierta, creamos una nueva comanda en blanco
        if (!$sale) {
            // Buscamos la serie por defecto para Notas de Venta Internas ('00')
            $serieRecord = \Percy\Core\Models\Serie::where('tenant_id', Auth::user()->tenant_id)
                ->where('document_type', '00')
                ->where('active', true)
                ->first();

            if (!$serieRecord) {
                \Filament\Notifications\Notification::make()
                    ->title('Falta Configuración')
                    ->body('Debes crear una Serie activa para "Nota de Venta (Interno)" (00) en la configuración.')
                    ->danger()
                    ->send();
                return;
            }

            // Incrementamos el correlativo
            $serieRecord->increment('correlative');

            // Creamos la comanda inicial con totales en cero
            $sale = \Percy\Core\Models\Sale::create([
                'tenant_id' => Auth::user()->tenant_id,
                'user_id' => Auth::id(),
                'table_id' => $table->id,
                'document_type' => '00',
                'series' => $serieRecord->serie,
                'correlative' => $serieRecord->correlative,
                'status' => 'pending', // 🌟 ESTADO CLAVE: Pendiente
                'sold_at' => now(),
                'op_gravadas' => 0,
                'op_exoneradas' => 0,
                'op_inafectas' => 0,
                'igv' => 0,
                'total' => 0,
                'payment_method' => 'Efectivo',
            ]);

        }

        // 3. Redirigimos al mozo a la pantalla de la comanda usando el método nativo de Filament
        return redirect()->to(PosOrder::getUrl(['sale' => $sale->id]));
    }
}
