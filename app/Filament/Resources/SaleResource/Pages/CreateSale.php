<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Percy\Core\Models\Serie;
use Percy\Core\Models\CashRegister;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    protected static ?string $title = 'Nueva Venta';

    protected ?string $maxContentWidth = 'full';

    public function mount(): void
    {
        parent::mount();

        // Validar que el usuario tenga una caja abierta
        $openCash = CashRegister::where('tenant_id', Auth::user()->tenant_id)
            ->where('user_id', Auth::id())
            ->where('status', 'open')
            ->exists();

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
        $serieRecord = Serie::where('document_type', $data['document_type'])
            ->where('serie', $data['series'])
            ->first();

        if (!$serieRecord) {
            throw new \Exception("La serie seleccionada no está configurada en el sistema.");
        }

        $serieRecord->increment('correlative');
        $data['correlative'] = $serieRecord->correlative;

        // 🌟 MAGIA DE LAS LETRAS: Generamos el texto aquí mismo para TODOS los documentos
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
