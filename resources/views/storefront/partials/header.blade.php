{{-- 🌟 CABECERA PRINCIPAL --}}
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4 py-3 flex justify-between items-center">

            {{-- 🌟 LOGO DINÁMICO --}}
            <div class="flex items-center gap-3 md:gap-5"> {{-- 🌟 1. Un poquito más de separación en PC --}}

                @if($tenant->logo)
                    {{-- 🌟 2. w-12 h-12 en celular | md:w-20 md:h-20 en PC (80x80px) --}}
                    <img src="{{ Storage::disk('r2_public')->url($tenant->logo) }}" alt="Logo {{ $tenant->name }}" class="w-12 h-12 md:w-20 md:h-20 object-contain rounded-full shadow-sm bg-white border border-gray-100">
                @else
                    {{-- 🌟 3. Igualamos el tamaño para la letra inicial, y agrandamos la letra (md:text-3xl) --}}
                    <div class="w-12 h-12 md:w-20 md:h-20 bg-brand/10 rounded-full flex items-center justify-center text-brand font-black text-xl md:text-3xl">
                        {{ substr($tenant->name, 0, 1) }}
                    </div>
                @endif

                {{-- 🌟 4. El título: text-xl en celular | md:text-2xl en PC --}}
                <h1 class="text-xl md:text-2xl font-black text-gray-800 leading-tight">
                    {{ $tenant->name }} <br>

                    {{-- 🌟 ESTADO DINÁMICO: ABIERTO / CERRADO --}}
                    @if($tenant->is_open_for_orders)
                        <span class="text-xs md:text-sm text-green-500 font-medium flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> Abierto
                        </span>
                    @else
                        <span class="text-xs md:text-sm text-red-500 font-medium flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-red-500"></span> Cerrado
                        </span>
                    @endif
                </h1>
            </div>

            {{-- Botón del Carrito --}}
            <button onclick="toggleCart()" class="relative p-2 bg-indigo-50 text-indigo-600 rounded-full hover:bg-indigo-100 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                <span id="cart-count" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full shadow-md">0</span>
            </button>
        </div>

        {{-- 🌟 BUSCADOR Y CATEGORÍAS (Scroll Horizontal) --}}
        <div class="max-w-6xl mx-auto px-4 pb-3">
            <div class="relative mb-3">
                <input type="text" id="searchInput" onkeyup="filterProducts()" placeholder="Buscar productos..." class="w-full bg-gray-100 border-transparent focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 rounded-xl py-2 pl-10 pr-4 text-sm transition-all">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>

            <div class="flex overflow-x-auto hide-scrollbar gap-2 pb-1" id="category-filters">
                {{-- 🌟 Pasamos 'this' para saber qué botón pintar --}}
                <button onclick="filterCategory('all', this)" class="category-btn active-category px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors">
                    Todos
                </button>
                @foreach($categories as $category)
                    <button onclick="filterCategory('{{ $category->name }}', this)" class="category-btn bg-gray-100 text-gray-600 hover:bg-gray-200 px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors">
                        {{ $category->name }}
                    </button>
                @endforeach
            </div>
        </div>
    </header>
