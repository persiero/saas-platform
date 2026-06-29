<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Percy\Core\Models\Sale;
use Percy\Core\Services\SunatService;

class SaleController extends Controller
{
    /**
     * Genera y muestra el ticket en PDF con QR y monto en letras.
     */
    public function printTicket(Sale $sale)
    {
        $this->authorizeTenantSale($sale);

        $sale->load([
            'items.product.unidadSunat',
            'tenant',
            'customer',
            'user',
        ]);

        if ($sale->sunat_pdf_path && Storage::disk('sunat')->exists($sale->sunat_pdf_path)) {
            return Storage::disk('sunat')->response($sale->sunat_pdf_path);
        }

        $qr_base64 = app(SunatService::class)->getQrCode($sale);

        $pdf = Pdf::loadView('pdf.ticket', [
            'sale' => $sale,
            'qr_base64' => $qr_base64,
        ]);

        $pdf->setPaper([0, 0, 226.77, 600], 'portrait');

        return $pdf->stream("Ticket-{$sale->series}-{$sale->correlative}.pdf");
    }

    public function downloadXml(Sale $sale)
    {
        $this->authorizeTenantSale($sale);

        if (!$sale->sunat_xml_path || !Storage::disk('sunat')->exists($sale->sunat_xml_path)) {
            abort(404, 'Archivo XML no encontrado.');
        }

        return Storage::disk('sunat')->download($sale->sunat_xml_path);
    }

    public function downloadCdr(Sale $sale)
    {
        $this->authorizeTenantSale($sale);

        if (!$sale->sunat_cdr_path || !Storage::disk('sunat')->exists($sale->sunat_cdr_path)) {
            abort(404, 'Archivo CDR no encontrado.');
        }

        return Storage::disk('sunat')->download($sale->sunat_cdr_path);
    }

    private function authorizeTenantSale(Sale $sale): void
    {
        $user = Auth::user();

        abort_unless($user, 403);

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return;
        }

        abort_unless((int) $user->tenant_id === (int) $sale->tenant_id, 403);
    }
}
