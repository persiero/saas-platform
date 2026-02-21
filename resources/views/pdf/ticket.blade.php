<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket de Venta</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
            width: 100%;
        }
        .ticket {
            width: 100%;
            max-width: 300px; /* Ancho típico de 80mm */
            margin: 0 auto;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        .divider {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 2px 0; font-size: 11px; }
        .totals-table td { font-size: 12px; }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="text-center">
            <h2 style="margin: 0; font-size: 16px;">MI NEGOCIO S.A.C.</h2>
            <p style="margin: 0;">RUC: 20123456789</p>
            <p style="margin: 0;">Av. Principal 123 - Trujillo</p>
            <div class="divider"></div>
            
            <h3 style="margin: 5px 0; font-size: 14px;">
                @if($sale->document_type == '01') FACTURA ELECTRÓNICA
                @elseif($sale->document_type == '03') BOLETA ELECTRÓNICA
                @else TICKET DE VENTA @endif
            </h3>
            <p class="bold" style="margin: 0;">{{ $sale->series }} - {{ str_pad($sale->correlative, 6, '0', STR_PAD_LEFT) }}</p>
            <div class="divider"></div>
        </div>

        <p style="margin: 2px 0;"><strong>Fecha:</strong> {{ $sale->sold_at->format('d/m/Y H:i') }}</p>
        <p style="margin: 2px 0;"><strong>Cliente:</strong> {{ $sale->customer ? $sale->customer->name : 'Público en General' }}</p>
        <div class="divider"></div>

        <table>
            <thead>
                <tr style="border-bottom: 1px solid #000;">
                    <th class="text-left">CANT</th>
                    <th class="text-left">DESCRIPCIÓN</th>
                    <th class="text-right">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $item)
                <tr>
                    <td class="text-left" style="vertical-align: top;">{{ number_format($item->quantity, 0) }}</td>
                    <td class="text-left">{{ $item->item_name }}</td>
                    <td class="text-right" style="vertical-align: top;">S/ {{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="divider"></div>

        <table class="totals-table">
            <tr>
                <td class="text-right">OP. GRAVADAS:</td>
                <td class="text-right">S/ {{ number_format($sale->op_gravadas, 2) }}</td>
            </tr>
            <tr>
                <td class="text-right">IGV (18%):</td>
                <td class="text-right">S/ {{ number_format($sale->igv, 2) }}</td>
            </tr>
            <tr>
                <td class="text-right bold" style="font-size: 14px;">TOTAL A PAGAR:</td>
                <td class="text-right bold" style="font-size: 14px;">S/ {{ number_format($sale->total, 2) }}</td>
            </tr>
        </table>
        <div class="divider"></div>
        
        <div class="text-center" style="margin-top: 10px;">
            <p style="margin: 0;">¡Gracias por su compra!</p>
            <p style="margin: 0; font-size: 10px;">Representación impresa del comprobante electrónico.</p>
        </div>
    </div>
</body>
</html>