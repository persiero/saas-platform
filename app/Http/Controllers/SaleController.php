<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Percy\Core\Models\Sale;
use Percy\Core\Services\SunatService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;


class SaleController extends Controller
{
    /**
     * Genera y muestra el ticket en PDF con QR y Monto en Letras.
     */
    public function printTicket(Sale $sale)
    {
        // Si el PDF ya fue guardado, lo servimos directamente
        if ($sale->sunat_pdf_path && Storage::disk('sunat')->exists($sale->sunat_pdf_path)) {
            return response()->file(Storage::disk('sunat')->path($sale->sunat_pdf_path));
        }

        // 1. Instanciamos el servicio que vive en el Core
        $sunatService = new SunatService();

        // 2. Generamos el código QR en formato Base64
        // Este método usa el sunat_hash que guardamos en Trujillo
        $qr_base64 = $sunatService->getQrCode($sale);

        // 3. Cargamos la vista del ticket pasando la venta y el QR
        $pdf = Pdf::loadView('pdf.ticket', [
            'sale' => $sale,
            'qr_base64' => $qr_base64
        ]);

        // 4. Configuramos el papel para ticketera de 80mm (226.77pt de ancho)
        // El largo (600pt) es una base, DomPDF lo ajustará según el contenido
        $pdf->setPaper([0, 0, 226.77, 600], 'portrait');

        // 5. Mostramos el PDF en el navegador
        return $pdf->stream("Ticket-{$sale->series}-{$sale->correlative}.pdf");
    }

    public function downloadXml(Sale $sale)
    {
        // Verificamos en nuestro disco seguro 'sunat'
        if (!$sale->sunat_xml_path || !Storage::disk('sunat')->exists($sale->sunat_xml_path)) {
            abort(404, 'Archivo XML no encontrado.');
        }
        // Obtenemos la ruta absoluta y forzamos la descarga (Amigable con Intelephense)
        $path = Storage::disk('sunat')->path($sale->sunat_xml_path);
        return response()->download($path);
    }

    public function downloadCdr(Sale $sale)
    {
        if (!$sale->sunat_cdr_path || !Storage::disk('sunat')->exists($sale->sunat_cdr_path)) {
            abort(404, 'Archivo CDR no encontrado.');
        }
        $path = Storage::disk('sunat')->path($sale->sunat_cdr_path);
        return response()->download($path);
    }
}
