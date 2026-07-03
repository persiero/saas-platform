<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Percy\Core\Services\Sales\CorrelativeService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Percy\Core\Services\Cash\CashRegisterService;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    protected static ?string $title = 'Nueva Venta';

    protected ?string $maxContentWidth = 'full';

    public function mount(): void
    {
        parent::mount();

        // Validar que el usuario tenga una caja abierta
        $openCash = app(CashRegisterService::class)
            ->openCashRegisterForTenant(Auth::user()->tenant_id);

        if (!$openCash) {
            Notification::make()
                ->title('Caja Cerrada')
                ->body('Debes abrir una caja antes de realizar ventas.')
                ->danger()
                ->persistent()
                ->send();

            $this->redirect(route('filament.admin.resources.cash-registers.index'));
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = Auth::user()->tenant_id;
        $userId = Auth::id();

        $cashRegister = app(CashRegisterService::class)
            ->requireOpenCashRegisterForTenant($tenantId);

        $data['tenant_id'] = $tenantId;
        $data['user_id'] = $userId;
        $data['cash_register_id'] = $cashRegister->id;

        $data['correlative'] = app(CorrelativeService::class)
            ->next($tenantId, $data['document_type'], $data['series']);

        if (isset($data['total'])) {
            $data['legend_text'] = $this->convertirTotalALetras((float) $data['total']);
        }

        return $data;
    }

    // 🌟 Función nativa para convertir a letras (Estilo SUNAT Perú)
    private function convertirTotalALetras(float $amount): string
    {
        // Usamos la clase nativa de PHP (requiere que la extensión 'intl' esté en tu php.ini, Laragon lo trae por defecto)
        if (class_exists('NumberFormatter')) {
            $formatter = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);

            $entero = floor($amount);
            $decimales = round(($amount - $entero) * 100);

            $letras = strtoupper($formatter->format($entero));

            // Correcciones gramaticales comunes en la facturación
            $letras = str_replace('VEINTIUNO', 'VEINTIÚN', $letras);
            $letras = preg_replace('/\bUNO\b/', 'UN', $letras);

            return $letras . " Y " . str_pad((string)$decimales, 2, '0', STR_PAD_LEFT) . "/100 SOLES";
        }

        // Fallback de seguridad extrema por si tu servidor no tiene NumberFormatter
        return number_format($amount, 2) . " SOLES";
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Registrar Venta')
                ->icon('heroicon-o-check-circle'),
            $this->getCancelFormAction()
                ->label('Cancelar'),
        ];
    }

    // 🌟 1. MAGIA: Disparamos el PDF en una pestaña nueva
    protected function afterCreate(): void
    {
        $sale = $this->record;
        $ticketUrl = url('/print/ticket/' . $sale->id);

        $this->js("
            window.open('{$ticketUrl}', '_blank');
        ");
    }

    // 🌟 2. Redirección nativa de Filament hacia la lista
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function beforeCreate(): void
    {
        $items = collect($this->data['items'] ?? [])
            ->filter(function (array $item): bool {
                return filled($item['product_id'] ?? null)
                    || filled($item['item_name'] ?? null);
            });

        if ($items->isEmpty()) {
            Notification::make()
                ->title('No puedes registrar una venta vacía')
                ->body('Agrega al menos un producto antes de registrar la venta.')
                ->warning()
                ->persistent()
                ->send();

            $this->halt();
        }

        if ((float) ($this->data['total'] ?? 0) <= 0) {
            Notification::make()
                ->title('El total de la venta no es válido')
                ->body('Verifica los productos, cantidades y precios antes de registrar la venta.')
                ->warning()
                ->persistent()
                ->send();

            $this->halt();
        }

        $this->data['op_gravadas'] = (float) ($this->data['op_gravadas'] ?? 0);
        $this->data['op_exoneradas'] = (float) ($this->data['op_exoneradas'] ?? 0);
        $this->data['op_inafectas'] = (float) ($this->data['op_inafectas'] ?? 0);
        $this->data['igv'] = (float) ($this->data['igv'] ?? 0);
        $this->data['total'] = (float) ($this->data['total'] ?? 0);
    }
}
