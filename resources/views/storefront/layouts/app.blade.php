<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tenant->name }} - Catálogo</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        /* Ocultar scrollbar */
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* 🌟 CLASES DE COLOR DINÁMICO */
        .bg-brand { background-color: {{ $tenant->primary_color ?? '#4f46e5' }} !important; }
        .text-brand { color: {{ $tenant->primary_color ?? '#4f46e5' }} !important; }
        .border-brand { border-color: {{ $tenant->primary_color ?? '#4f46e5' }} !important; }

        /* Botón de categoría activo */
        .active-category {
            background-color: {{ $tenant->primary_color ?? '#4f46e5' }} !important;
            color: white !important;
        }
    </style>

</head>
<body class="bg-gray-50 pb-24">

    {{-- Aquí insertamos la cabecera --}}
    @include('storefront.partials.header')

    {{-- Aquí irá el contenido dinámico (El index) --}}
    @yield('content')

    {{-- Aquí insertamos el Footer y el Carrito --}}
    @include('storefront.partials.footer')
    @include('storefront.partials.cart')

</body>
</html>
