@extends('storefront.layouts.app')

@section('content')
    {{-- 🌟 CATÁLOGO DE PRODUCTOS --}}
    <main class="max-w-6xl mx-auto px-4 py-8">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6" id="products-grid">
            @foreach($products as $product)
                {{-- 🌟 TRADUCTOR DE UNIDADES SUNAT A NOMBRES AMIGABLES --}}
                @php
                    $codigoSunat = $product->unidadSunat->codigo ?? 'NIU';
                    $unidadAmigable = match($codigoSunat) {
                        'NIU' => 'Und.',
                        'KGM' => 'Kg.',
                        'LTR' => 'Lt.',
                        'GLL' => 'Galón',
                        'GRM' => 'g.',
                        'MTR' => 'm.',
                        'BX'  => 'Caja',
                        'PK'  => 'Paquete',
                        default => $codigoSunat // Si es otro raro, lo deja como está
                    };
                @endphp

                <div class="product-card bg-white rounded-2xl shadow-sm border border-gray-100 p-2 sm:p-3 flex flex-col h-full transition-all duration-300 hover:shadow-xl hover:-translate-y-1 relative group overflow-hidden"
                     data-name="{{ strtolower($product->name) }}"
                     data-category="{{ strtolower($product->category->name ?? 'General') }}">

                    {{-- 🌟 IMAGEN (Ajustada para que no sea tan gigante en móvil) --}}
                    <div class="relative w-full h-32 sm:h-44 rounded-xl mb-2 sm:mb-3 overflow-hidden bg-gray-50 flex items-center justify-center">
                        @if($product->image)
                            <img src="{{ Storage::disk('r2_public')->url($product->image) }}" alt="{{ $product->name }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                        @else
                            <svg class="w-10 h-10 sm:w-12 sm:h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        @endif

                        {{-- BADGE DE UNIDAD --}}
                        <div class="absolute top-2 right-2 bg-white/90 backdrop-blur-sm px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-lg shadow-sm border border-gray-100 text-[9px] sm:text-[10px] font-black text-gray-700 uppercase tracking-wider">
                            X {{ $unidadAmigable }}
                        </div>
                    </div>

                    {{-- 🌟 TEXTOS (Con altura mínima forzada para alinear todo) --}}
                    <div class="px-1 flex-grow flex flex-col">
                        <p class="text-[10px] sm:text-xs font-semibold text-brand mb-1 uppercase tracking-wider line-clamp-1">{{ $product->category->name ?? 'General' }}</p>

                        {{-- TRUCO: min-h-[2.5rem] fuerza a que ocupen el espacio de 2 líneas siempre --}}
                        <h3 class="text-xs sm:text-sm font-bold text-gray-800 leading-tight line-clamp-2 mb-2 min-h-[2rem] sm:min-h-[2.5rem]">{{ $product->name }}</h3>
                    </div>

                    {{-- 🌟 PRECIO Y BOTÓN (Responsivo: Apilado en móvil pequeño, lado a lado en móvil grande/PC) --}}
                    <div class="px-1 mt-auto pt-2 flex flex-col min-[400px]:flex-row items-start min-[400px]:items-end justify-between border-t border-gray-50 gap-2 min-[400px]:gap-0">
                        <div class="flex flex-col w-full min-[400px]:w-auto">
                            <span class="text-[10px] sm:text-xs text-gray-400 font-medium line-through hidden">S/ {{ number_format($product->price * 1.2, 2) }}</span>
                            <span class="font-black text-sm sm:text-lg text-gray-900 leading-none truncate w-full">S/ {{ number_format($product->price, 2) }}</span>
                        </div>

                        {{-- Botón (Se estira en móvil, tamaño normal en PC) --}}
                        <button onclick="addToCart({{ $product->id }}, @js($product->name), {{ $product->price }}, @js($unidadAmigable))"
                                class="w-full min-[400px]:w-auto bg-brand text-white text-xs sm:text-sm px-3 py-1.5 sm:px-4 sm:py-2 rounded-xl flex items-center justify-center hover:opacity-90 transition font-bold shadow-sm shadow-brand/30 active:scale-95">
                            Agregar
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </main>

    <script>
        // 🌟 LÓGICA DE FILTROS Y BÚSQUEDA
        let currentCategory = 'all';

        // 1. Filtrar al escribir en el buscador
        function filterProducts() {
            applyFilters();
        }

        // 2. Filtrar al hacer clic en las "píldoras" de categorías
        function filterCategory(category, btn) {
            currentCategory = category.toLowerCase();

            // Limpiar el estilo visual de todos los botones
            let allButtons = document.querySelectorAll('.category-btn');
            allButtons.forEach(button => {
                button.classList.remove('active-category', 'bg-brand', 'text-white'); // Ajusta según tu color
                button.classList.add('bg-gray-100', 'text-gray-600');
            });

            // Ponerle el estilo de "Activo" al botón seleccionado
            btn.classList.remove('bg-gray-100', 'text-gray-600');
            btn.classList.add('active-category', 'bg-brand', 'text-white'); // Usa tu clase de color principal

            applyFilters();
        }

        // 3. El motor principal que cruza Búsqueda + Categoría
        function applyFilters() {
            let searchInput = document.getElementById('searchInput');
            if (!searchInput) return; // Por seguridad, si no existe el buscador no hace nada

            let searchTerm = searchInput.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, ""); // Ignora tildes
            let products = document.querySelectorAll('.product-card'); // Asegúrate de que tus tarjetas tengan esta clase

            products.forEach(card => {
                // Leemos los atributos de la tarjeta
                let productName = (card.getAttribute('data-name') || '').toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                let productCategory = (card.getAttribute('data-category') || '').toLowerCase();

                // Verificamos si cumple ambas condiciones
                let matchesSearch = productName.includes(searchTerm);
                let matchesCategory = (currentCategory === 'all' || productCategory === currentCategory);

                // Mostrar u ocultar (Usamos 'flex' asumiendo que tus tarjetas tienen flex-col o similar)
                if (matchesSearch && matchesCategory) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
@endsection
