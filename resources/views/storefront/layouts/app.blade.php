<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tenant->name }} - Catálogo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

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

    {{-- 🌟 BOTÓN FLOTANTE WHATSAPP --}}
    @if($tenant->phone)
        @php
            // Limpiamos el número para que sea solo dígitos (Ej: +51 936... -> 51936...)
            // Asumimos código de Perú (51) si no lo tiene
            $numeroLimpio = preg_replace('/[^0-9]/', '', $tenant->phone);
            if (strlen($numeroLimpio) == 9) $numeroLimpio = '51' . $numeroLimpio;
        @endphp
        <a href="https://wa.me/{{ $numeroLimpio }}?text=Hola,%20vengo%20de%20la%20tienda%20online.%20Necesito%20información."
           target="_blank"
           class="fixed bottom-6 right-6 bg-[#25D366] text-white w-14 h-14 rounded-full shadow-lg shadow-[#25D366]/40 flex items-center justify-center hover:scale-110 transition-transform z-50 group">

            {{-- Tooltip emergente al pasar el mouse --}}
            <span class="absolute right-16 bg-white text-gray-800 text-xs font-bold px-3 py-1.5 rounded-lg shadow-md opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
                ¿Necesitas ayuda?
            </span>

            {{-- Ícono de WhatsApp --}}
            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.614-.087-.112-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.274.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.418-.099.824z"/></svg>
        </a>
    @endif
</body>
</html>
