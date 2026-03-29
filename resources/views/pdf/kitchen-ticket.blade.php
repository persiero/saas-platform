<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket de Cocina - {{ $sale->table->name ?? 'Mesa' }}</title>
    <style>
        /* 🌟 MAGIA: Le dice al navegador el tamaño físico del papel */
        @page {
            size: 80mm auto; /* Ancho de ticketera estándar, alto automático */
            margin: 0mm; /* Cero márgenes para no desperdiciar papel térmico */
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            width: 74mm; /* Un poco menos de 80mm para dar margen de impresión */
            margin: 0 auto;
            padding: 2mm;
            color: #000;
            font-size: 14px;
        }

        /* Resto de tus estilos... */
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .text-xl { font-size: 24px; }
        .text-lg { font-size: 18px; }
        .mb-2 { margin-bottom: 8px; }
        .mt-2 { margin-top: 8px; }
        .border-top { border-top: 1px dashed #000; padding-top: 5px; }
        .border-bottom { border-bottom: 1px dashed #000; padding-bottom: 5px; margin-bottom: 10px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { text-align: left; padding: 4px 0; vertical-align: top; }
        th { border-bottom: 1px solid #000; }

        /* Tamaños de columnas para que resalte la cantidad y el plato */
        .qty-col { width: 20%; text-align: center; font-weight: bold; font-size: 18px; }
        .item-col { width: 80%; padding-left: 5px; font-size: 16px; font-weight: bold; }

    </style>
</head>
<body> <div class="text-center mb-2 border-bottom">
        <h2 style="margin: 0;">*** A COCINA ***</h2>
    </div>

    <div class="mb-2">
        <div class="font-bold text-xl text-center mb-2">
            {{ strtoupper($sale->table->name ?? 'LLEVAR') }}
        </div>
        <div><span class="font-bold">ZONA:</span> {{ $sale->table->zone->name ?? '---' }}</div>
        <div><span class="font-bold">MOZO:</span> {{ $sale->user->name ?? 'Cajero' }}</div>
        <div><span class="font-bold">FECHA:</span> {{ now()->format('d/m/Y H:i') }}</div>
        <div><span class="font-bold">COMANDA:</span> {{ $sale->series ?? '00' }}-{{ $sale->correlative ?? '00' }}</div>
    </div>

    <table class="border-top">
        <thead>
            <tr>
                <th class="qty-col">CANT</th>
                <th class="item-col">DESCRIPCIÓN</th>
            </tr>
        </thead>
        <tbody>
                @foreach($sale->items as $item)
                <tr>
                    <td class="qty-col">[{{ floatval($item->quantity) }}]</td>
                    <td class="item-col">
                        {{ strtoupper($item->item_name) }}

                        {{-- 🌟 MAGIA: Si hay nota, la imprimimos bien llamativa --}}
                        @if($item->note)
                            <br>
                            <span style="font-size: 14px; font-weight: normal; font-style: italic;">
                                >> *{{ strtoupper($item->note) }}*
                            </span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="text-center mt-2 border-top">
        <p>FIN DE LA ORDEN</p>
    </div>

</body>
</html>
