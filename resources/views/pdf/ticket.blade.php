<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket {{ $sale->series }}-{{ $sale->correlative }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; margin: 0; padding: 0; width: 100%; }
        .ticket { width: 100%; max-width: 280px; margin: 0 auto; padding: 10px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 2px 0; font-size: 11px; }
        .totals-table td { font-size: 12px; }
        .qr-container { margin-top: 10px; margin-bottom: 5px; }
        .reference-box { font-size: 10px; margin: 5px 0; text-align: left; }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="text-center">
            <h2 style="margin: 0; font-size: 14px; text-transform: uppercase;">{{ $sale->tenant->business_name }}</h2>
            <p style="margin: 0;">RUC: {{ $sale->tenant->ruc }}</p>
            <p style="margin: 0;">{{ $sale->tenant->address }}</p>
            <div class="divider"></div>

            {{-- 1. TÍTULO DINÁMICO DEL COMPROBANTE --}}
            <h3 style="margin: 5px 0; font-size: 12px;">
                @if($sale->document_type == '01') FACTURA ELECTRÓNICA
                @elseif($sale->document_type == '03') BOLETA ELECTRÓNICA
                @elseif($sale->document_type == '07') NOTA DE CRÉDITO ELECTRÓNICA
                @elseif($sale->document_type == '08') NOTA DE DÉBITO ELECTRÓNICA
                @else NOTA DE VENTA
                @endif
            </h3>
            <p class="bold" style="margin: 0;">{{ $sale->series }} - {{ str_pad($sale->correlative, 8, '0', STR_PAD_LEFT) }}</p>
            <div class="divider"></div>
        </div>

        {{-- 2. BLOQUE EXCLUSIVO PARA NOTAS DE CRÉDITO/DÉBITO --}}
        @if(in_array($sale->document_type, ['07', '08']))
            <div class="reference-box">
                <p style="margin: 2px 0;"><strong>Doc. Modificado:</strong> {{ $sale->affected_document_series }}-{{ $sale->affected_document_correlative }}</p>
                <p style="margin: 2px 0;"><strong>Motivo:</strong> {{ $sale->cancel_reason_description }}</p>
            </div>
            <div class="divider"></div>
        @endif

        <p style="margin: 2px 0;"><strong>Fecha:</strong> {{ $sale->sold_at->format('d/m/Y H:i') }}</p>
        <p style="margin: 2px 0;"><strong>Cliente:</strong> {{ $sale->customer ? $sale->customer->name : 'PÚBLICO EN GENERAL' }}</p>
        {{-- 1. ETIQUETA INTELIGENTE PARA RUC/DNI --}}
        <p style="margin: 2px 0;">
            <strong>{{ in_array(strtoupper(trim($sale->customer?->document_type ?? '')), ['6', 'RUC']) ? 'RUC' : 'DNI' }}:</strong>
            {{ $sale->customer?->document_number ?? '00000000' }}
        </p>
        {{-- 2. DIRECCIÓN (SOLO SI ES FACTURA Y EL CLIENTE TIENE DIRECCIÓN) --}}
        @if($sale->document_type == '01' && $sale->customer?->address)
            <p style="margin: 2px 0;"><strong>Dirección:</strong> {{ $sale->customer->address }}</p>
        @endif
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
                    <td class="text-right" style="vertical-align: top;">{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="divider"></div>

        <table class="totals-table">
            <tr>
                <td class="text-right">OP. GRAVADAS:</td>
                <td class="text-right">{{ $sale->currency }} {{ number_format($sale->op_gravadas, 2) }}</td>
            </tr>
            <tr>
                <td class="text-right">IGV (18%):</td>
                <td class="text-right">{{ $sale->currency }} {{ number_format($sale->igv, 2) }}</td>
            </tr>
            <tr>
                <td class="text-right bold" style="font-size: 13px;">TOTAL A PAGAR:</td>
                <td class="text-right bold" style="font-size: 13px;">{{ $sale->currency }} {{ number_format($sale->total, 2) }}</td>
            </tr>
        </table>

        <div style="margin-top: 10px; font-size: 10px;" class="text-left">
            <strong>SON:</strong> {{ $sale->legend_text }}
        </div>

        <div class="divider"></div>

        <div class="text-center">
            <div class="qr-container">
                <img src="{{ $qr_base64 }}" style="width: 100px;">
            </div>
            <p style="margin: 0; font-size: 9px;">Hash: {{ $sale->sunat_hash }}</p>

            {{-- 3. PIE DE PÁGINA DINÁMICO --}}
            <p style="margin: 5px 0 0 0; font-size: 9px;">
                Representación impresa de la
                @if($sale->document_type == '01') Factura
                @elseif($sale->document_type == '03') Boleta
                @elseif($sale->document_type == '07') Nota de Crédito
                @elseif($sale->document_type == '08') Nota de Débito
                @else Comprobante
                @endif
                Electrónica.
            </p>
            <p style="margin: 0; font-size: 9px;">¡Gracias por su preferencia!</p>
        </div>
    </div>
</body>
</html>
