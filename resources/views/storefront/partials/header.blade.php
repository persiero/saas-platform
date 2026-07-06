@php
    $numeroLimpioHeader = $tenant->phone ? preg_replace('/[^0-9]/', '', $tenant->phone) : null;

    if ($numeroLimpioHeader && strlen($numeroLimpioHeader) === 9) {
        $numeroLimpioHeader = '51' . $numeroLimpioHeader;
    }

    $isHomePage = request()->routeIs('storefront.index');
    $isProductsPage = request()->routeIs('storefront.products');
@endphp

<header class="bg-white/95 backdrop-blur-xl sticky top-0 z-50 border-b border-slate-100 shadow-sm">
    {{-- FILA PRINCIPAL --}}
    <div class="max-w-6xl mx-auto px-4 py-3">
        <div class="flex items-center justify-between gap-3">

            {{-- LOGO + NOMBRE --}}
            <a href="/" class="flex items-center gap-3 min-w-0 hover:opacity-90 transition">
                @if($tenant->logo)
                    <img
                        src="{{ Storage::disk('r2_public')->url($tenant->logo) }}"
                        alt="Logo {{ $tenant->name }}"
                        class="w-14 h-14 md:w-16 md:h-16 object-contain rounded-2xl bg-white border border-slate-100 shadow-sm shrink-0"
                    >
                @else
                    <div class="w-14 h-14 md:w-16 md:h-16 bg-brand-soft rounded-2xl flex items-center justify-center text-brand font-black text-xl md:text-2xl shrink-0 border border-slate-100">
                        {{ substr($tenant->name, 0, 1) }}
                    </div>
                @endif

                <div class="min-w-0">
                    <h1 class="text-sm md:text-lg xl:text-xl font-black text-slate-900 leading-tight truncate">
                        {{ $tenant->name }}
                    </h1>

                    <div class="flex items-center gap-2 mt-1">
                        @if($tenant->is_open_for_orders)
                            <span class="inline-flex items-center gap-1.5 text-[10px] md:text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded-full">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                Abierto
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-[10px] md:text-xs font-bold text-red-700 bg-red-50 border border-red-100 px-2 py-0.5 rounded-full">
                                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                Cerrado
                            </span>
                        @endif

                        <span class="hidden sm:inline text-xs font-semibold text-slate-500">
                            Catálogo online
                        </span>
                    </div>
                </div>
            </a>

            {{-- ACCIONES --}}
            <div class="flex items-center gap-2 shrink-0">
                @isset($categories)
                    @if($isProductsPage || $isHomePage)
                        <div class="relative hidden lg:block w-[340px] xl:w-[430px]">
                            <input
                                type="text"
                                id="{{ $isProductsPage ? 'mobileSearchInput' : 'searchInput' }}"
                                onkeyup="{{ $isProductsPage ? 'syncMobileProductSearch(this.value)' : 'filterProducts()' }}"
                                placeholder="{{ $isProductsPage ? 'Buscar productos...' : 'Busca un producto o entra al catálogo...' }}"
                                class="w-full bg-slate-50 border border-slate-200 focus:bg-white focus-brand rounded-2xl py-2.5 pl-10 pr-10 text-sm transition-all outline-none"
                            >

                            <button
                                type="button"
                                onclick="filterProducts()"
                                class="absolute left-3 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center text-slate-400 hover:text-brand transition"
                                aria-label="Buscar"
                            >
                                <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                            </button>

                            <button
                                type="button"
                                onclick="resetFilters()"
                                class="absolute right-3 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center text-slate-400 hover:text-red-500 transition"
                                aria-label="Limpiar búsqueda"
                            >
                                <x-heroicon-o-x-mark class="w-4 h-4" />
                            </button>
                        </div>
                    @endif
                @endisset

                @if($tenant->phone && $numeroLimpioHeader)
                    <a
                        href="https://wa.me/{{ $numeroLimpioHeader }}?text=Hola,%20vengo%20de%20la%20tienda%20online.%20Necesito%20información."
                        target="_blank"
                        class="hidden sm:inline-flex items-center gap-2 bg-emerald-50 text-emerald-700 border border-emerald-100 font-bold px-3 md:px-4 py-2.5 rounded-2xl hover:bg-emerald-100 transition"
                    >
                        <x-heroicon-o-chat-bubble-left-right class="w-5 h-5" />
                        <span class="hidden md:inline">WhatsApp</span>
                    </a>
                @endif

                @isset($categories)
                    <button
                        type="button"
                        onclick="toggleSearchPanel()"
                        class="inline-flex items-center justify-center w-11 h-11 lg:hidden bg-slate-100 text-slate-700 rounded-2xl hover:bg-slate-200 transition"
                        aria-label="Buscar productos"
                    >
                        <x-heroicon-o-magnifying-glass class="w-5 h-5" />
                    </button>
                @endisset

                <button
                    onclick="toggleCart()"
                    class="relative h-11 md:h-12 px-3 md:px-4 bg-brand text-white rounded-2xl hover:opacity-95 active:scale-95 transition flex items-center justify-center gap-2 shadow-brand"
                >
                    <x-heroicon-o-shopping-cart class="w-5 h-5 md:w-6 md:h-6" />

                    <span class="hidden sm:inline font-black text-sm">
                        Carrito
                    </span>

                    <span
                        id="cart-count"
                        class="absolute -top-1 -right-1 bg-red-500 text-white text-[11px] font-black min-w-5 h-5 px-1 rounded-full shadow-md flex items-center justify-center"
                    >
                        0
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- FILA DE BÚSQUEDA COMPACTA --}}
    @isset($categories)
        <div id="search-panel" class="hidden border-t border-slate-100 bg-white">
            <div class="max-w-6xl mx-auto px-4 py-3">
                <div class="flex items-center gap-3">
                                        
                    {{-- Buscador --}}
                    <div class="relative flex-1">
                        <input
                            type="text"
                            id="searchInput"
                            onkeyup="filterProducts()"
                            placeholder="{{ $isProductsPage ? 'Buscar productos por nombre o categoría...' : 'Busca un producto o entra al catálogo completo...' }}"
                            class="w-full bg-slate-50 border border-slate-200 focus:bg-white focus-brand rounded-2xl py-3 pl-11 pr-11 text-sm transition-all outline-none"
                        >

                        {{-- Lupa interna --}}
                        <button
                            type="button"
                            onclick="filterProducts()"
                            class="absolute left-3 top-1/2 -translate-y-1/2 w-7 h-7 flex items-center justify-center text-slate-400 hover:text-brand transition"
                            aria-label="Buscar"
                        >
                            <x-heroicon-o-magnifying-glass class="w-5 h-5" />
                        </button>

                        {{-- Limpiar interno --}}
                        <button
                            type="button"
                            onclick="resetFilters()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 w-7 h-7 flex items-center justify-center text-slate-400 hover:text-red-500 transition"
                            aria-label="Limpiar búsqueda"
                        >
                            <x-heroicon-o-x-mark class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            window.syncMobileProductSearch = function (value) {
                const desktopInput = document.getElementById('searchInput');

                if (desktopInput) {
                    desktopInput.value = value;
                }

                if (typeof filterProducts === 'function') {
                    filterProducts();
                }
            };

            window.toggleSearchPanel = function () {
                const panel = document.getElementById('search-panel');

                if (! panel) {
                    return;
                }

                panel.classList.toggle('hidden');
                panel.classList.toggle('block');
            };

            window.toggleCategoryDropdown = function () {
                const dropdown = document.getElementById('categoryDropdown');

                if (! dropdown) {
                    return;
                }

                dropdown.classList.toggle('hidden');
            };

            window.selectHeaderCategory = function (value, label) {
                const input = document.getElementById('categorySelect');
                const labelElement = document.getElementById('categorySelectLabel');
                const dropdown = document.getElementById('categoryDropdown');

                if (input) {
                    input.value = value;
                }

                if (labelElement) {
                    labelElement.innerText = label;
                }

                if (dropdown) {
                    dropdown.classList.add('hidden');
                }

                if (typeof filterCategoryFromSelect === 'function') {
                    filterCategoryFromSelect(value);
                }
            };

            document.addEventListener('click', function (event) {
                const dropdown = document.getElementById('categoryDropdown');
                const button = event.target.closest('[onclick="toggleCategoryDropdown()"]');
                const insideDropdown = event.target.closest('#categoryDropdown');

                if (! dropdown) {
                    return;
                }

                if (! button && ! insideDropdown) {
                    dropdown.classList.add('hidden');
                }
            });
        </script>
    @endisset
</header>