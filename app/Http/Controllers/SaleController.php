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
        // 🌟 LA CURA AL LAZY LOADING: Cargamos todas las relaciones necesarias para el ticket
        $sale->load(['items.product.unidadSunat', 'tenant', 'customer', 'user']);

        // Si el PDF ya fue guardado en la nube, lo mostramos directamente
        if ($sale->sunat_pdf_path && Storage::disk('sunat')->exists($sale->sunat_pdf_path)) {
            // Usamos ->response() para mostrar el archivo de Cloudflare en el navegador
            return Storage::disk('sunat')->response($sale->sunat_pdf_path);
        }

        // 1. Instanciamos el servicio que vive en el Core
        $sunatService = new \Percy\Core\Services\SunatService();

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
        // 🌟 ESTO DESCARGA DIRECTO DESDE CLOUDFLARE R2
        return Storage::disk('sunat')->download($sale->sunat_xml_path);
    }

    public function downloadCdr(Sale $sale)
    {
        if (!$sale->sunat_cdr_path || !Storage::disk('sunat')->exists($sale->sunat_cdr_path)) {
            abort(404, 'Archivo CDR no encontrado.');
        }
        // 🌟 ESTO DESCARGA DIRECTO DESDE CLOUDFLARE R2
        return Storage::disk('sunat')->download($sale->sunat_cdr_path);
    }
}
