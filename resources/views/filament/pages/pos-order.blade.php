<x-filament-panels::page>
    {{-- 🌟 CONTENEDOR PRINCIPAL: Volvemos a items-start para que el ticket flote bien --}}
    <div class="flex flex-col lg:flex-row gap-6 items-start w-full">

        {{-- 🌟 PANEL IZQUIERDO: PRODUCTOS (w-3/4 asegura el 75% del monitor exacto) --}}
        <div class="pos-panel-izquierdo flex flex-col gap-4">

            {{-- Encabezado con datos de la mesa --}}
            <div
                class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-black text-gray-800 dark:text-gray-100">
                        {{ $sale->table->name ?? 'Mesa' }}
                        <span
                            class="text-sm font-normal text-gray-500 ml-2">({{ $sale->table->zone->name ?? 'Zona' }})</span>
                    </h1>
                    <p class="text-xs text-gray-500">Comanda: {{ $sale->series }}-{{ $sale->correlative }}</p>
                </div>
                <x-filament::button href="{{ route('filament.admin.pages.pos-restaurant') }}" tag="a" color="gray"
                    icon="heroicon-m-arrow-left" class="w-full sm:w-auto">
                    Volver a mesas
                </x-filament::button>
            </div>

            {{-- Buscador de productos --}}
            <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative flex-1">
                        <x-heroicon-o-magnifying-glass
                            class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />

                        <input type="search" wire:model.live.debounce.300ms="productSearch"
                            placeholder="Buscar plato, bebida o producto..."
                            class="pos-search-input w-full rounded-xl border-gray-300 bg-white py-2.5 text-sm font-medium text-gray-900 shadow-sm outline-none transition focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-400">

                        @if (filled($productSearch))
                            <button type="button" wire:click="clearProductSearch"
                                class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                                title="Limpiar búsqueda">
                                <x-heroicon-o-x-mark class="h-4 w-4" />
                            </button>
                        @endif
                    </div>

                    <div
                        class="flex items-center justify-between gap-2 text-xs font-bold text-gray-500 dark:text-gray-400 sm:justify-end">
                        <span class="rounded-full bg-gray-100 px-3 py-1 dark:bg-gray-800">
                            {{ $this->products->count() }} resultados
                        </span>
                    </div>
                </div>
            </div>

            {{-- Categorías --}}
            <div class="flex overflow-x-auto gap-2 pb-2 hide-scrollbar">
                <button wire:click="setCategory(null)"
                    class="whitespace-nowrap px-6 py-2 rounded-full font-bold text-sm transition-colors shadow-sm {{ is_null($selectedCategoryId) ? 'bg-primary-600 text-white border-transparent' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                    Todos
                </button>
                @foreach ($this->categories as $category)
                    <button wire:click="setCategory({{ $category->id }})"
                        class="whitespace-nowrap px-6 py-2 rounded-full font-bold text-sm transition-colors shadow-sm {{ $selectedCategoryId === $category->id ? 'bg-primary-600 text-white border-transparent' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                        {{ $category->name }}
                    </button>
                @endforeach
            </div>

            {{-- GRILLA DE PLATOS --}}
            <div
                class="flex-1 min-h-[50vh] overflow-y-auto rounded-xl border border-gray-200 bg-gray-50 p-3 shadow-inner dark:border-gray-800 dark:bg-gray-900 sm:p-4">
                <div class="pos-products-grid">
                    @forelse($this->products as $product)
                        @php
                            // 🌟 MAGIA UX: Calculamos el estado de stock
                            $isProduct = $product->type === 'product';
                            $stock = (float) ($product->current_stock ?? 0);
                            $isOutOfStock = $isProduct && $stock <= 0;
                        @endphp

                        <button @if (!$isOutOfStock) wire:click="addProduct({{ $product->id }})" @endif
                            @disabled($isOutOfStock)
                            class="pos-product-card relative rounded-xl border p-3 text-left shadow-sm transition
        {{ $isOutOfStock
            ? 'cursor-not-allowed border-gray-200 bg-gray-100 opacity-60 dark:border-gray-800 dark:bg-gray-800/50'
            : 'cursor-pointer border-gray-200 bg-white hover:border-primary-500 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-400' }}">
                            <div class="flex h-full flex-col justify-between gap-2">
                                <div class="flex items-start gap-2">
                                    {{-- Imagen pequeña solo desde tablet. En celular se oculta para ahorrar espacio. --}}
                                    <div
                                        class="hidden h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-100 bg-gray-50 dark:border-gray-700 dark:bg-gray-700 lg:flex">
                                        @if ($product->image)
                                            <img src="{{ Storage::disk('r2_public')->url($product->image) }}"
                                                alt="{{ $product->name }}" class="h-full w-full object-cover">
                                        @else
                                            <x-heroicon-o-cube class="h-5 w-5 text-gray-300 dark:text-gray-500" />
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <span
                                            class="pos-product-name text-sm font-black leading-tight
                    {{ $isOutOfStock ? 'text-gray-500 dark:text-gray-500' : 'text-gray-900 dark:text-gray-100' }}">
                                            {{ $product->name }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-end justify-between gap-2">
                                    <span
                                        class="text-sm font-black {{ $isOutOfStock ? 'text-gray-400 dark:text-gray-600' : 'text-primary-600 dark:text-primary-400' }}">
                                        S/ {{ number_format($product->price, 2) }}
                                    </span>

                                    @if ($isProduct)
                                        @if ($isOutOfStock)
                                            <span
                                                class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-red-600 dark:bg-red-900/40 dark:text-red-400">
                                                Agotado
                                            </span>
                                        @else
                                            <span
                                                class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold text-gray-500 dark:bg-gray-700 dark:text-gray-300">
                                                {{ $stock }} und
                                            </span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </button>
                    @empty
                        <div
                            class="col-span-full flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white py-12 text-center dark:border-gray-700 dark:bg-gray-900">
                            <x-heroicon-o-magnifying-glass class="mb-3 h-10 w-10 text-gray-300 dark:text-gray-600" />

                            <h3 class="text-sm font-black text-gray-700 dark:text-gray-200">
                                No se encontraron productos
                            </h3>

                            <p class="mt-1 max-w-xs text-xs text-gray-500 dark:text-gray-400">
                                Prueba con otro nombre o cambia de categoría.
                            </p>

                            @if (filled($productSearch))
                                <button type="button" wire:click="clearProductSearch"
                                    class="mt-4 rounded-full bg-primary-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-primary-700">
                                    Limpiar búsqueda
                                </button>
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- 🌟 PANEL DERECHO: TICKET (w-1/4 asegura el 25% del monitor exacto) --}}
        <div
            class="pos-panel-derecho flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:sticky lg:top-6 lg:min-w-[280px]">

            <div class="p-4 bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 rounded-t-xl">
                <h2 class="font-black text-lg text-gray-800 dark:text-white uppercase tracking-wide">Comanda</h2>
            </div>

            {{-- Lista de items --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-4 max-h-[50vh] lg:max-h-[calc(100vh-24rem)]">
                @forelse($sale->items as $item)
                    <div
                        class="flex justify-between items-start pb-4 border-b border-gray-100 dark:border-gray-800 last:border-0 last:pb-0">

                        {{-- 🌟 COLUMNA IZQUIERDA (Nombre, Notas y Controles) --}}
                        <div class="flex-1 pr-2">
                            <h3 class="font-bold text-sm text-gray-800 dark:text-gray-200 leading-tight">
                                {{ $item->item_name }}
                            </h3>

                            {{-- 🌟 MAGIA UX: Botón de nota y visualización --}}
                            <div class="mt-1">
                                @if ($item->note)
                                    <p
                                        class="text-xs text-red-500 dark:text-red-400 italic font-medium leading-tight mb-1">
                                        * {{ $item->note }}
                                    </p>
                                @endif

                                <button type="button"
                                    onclick="let nota = prompt('Instrucción para cocina (Ej: Sin cebolla, bien frito):', '{{ $item->note }}'); if(nota !== null) { @this.updateItemNote({{ $item->id }}, nota) }"
                                    class="text-[10px] text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 font-bold uppercase tracking-wider flex items-center gap-1 cursor-pointer">
                                    <x-heroicon-o-pencil-square class="w-3 h-3" />
                                    {{ $item->note ? 'Editar Nota' : '+ Agregar Nota' }}
                                </button>
                            </div>

                            {{-- Controles de cantidad --}}
                            <div class="flex items-center gap-2 mt-3">
                                <button wire:click="decrementItem({{ $item->id }})"
                                    class="w-7 h-7 flex items-center justify-center bg-gray-200 dark:bg-gray-800 rounded-full text-gray-800 dark:text-gray-200 font-black hover:bg-gray-300 dark:hover:bg-gray-700 transition cursor-pointer">
                                    -
                                </button>
                                <span
                                    class="text-sm font-bold w-6 text-center text-gray-800 dark:text-gray-200">{{ floatval($item->quantity) }}</span>
                                <button wire:click="incrementItem({{ $item->id }})"
                                    class="w-7 h-7 flex items-center justify-center bg-gray-200 dark:bg-gray-800 rounded-full text-gray-800 dark:text-gray-200 font-black hover:bg-gray-300 dark:hover:bg-gray-700 transition cursor-pointer">
                                    +
                                </button>
                            </div>
                        </div>

                        {{-- COLUMNA DERECHA (Precio y Quitar) --}}
                        <div class="text-right flex flex-col items-end">
                            <span class="font-black text-sm text-gray-800 dark:text-gray-200">S/
                                {{ number_format($item->total, 2) }}</span>
                            <button wire:click="removeItem({{ $item->id }})"
                                class="text-xs text-red-500 hover:text-red-700 dark:hover:text-red-400 mt-2 font-bold flex items-center gap-1 cursor-pointer">
                                <x-heroicon-o-trash class="w-4 h-4" /> Quitar
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="py-12 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                        <x-heroicon-o-shopping-cart class="w-10 h-10 mb-3 opacity-50" />
                        <p class="text-sm font-medium">Venta vacía</p>
                    </div>
                @endforelse
            </div>

            {{-- Pie del Ticket --}}
            <div class="p-4 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 rounded-b-xl">

                <div
                    class="flex justify-between items-end bg-white dark:bg-gray-900 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 mb-4">
                    <span
                        class="text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">Total</span>
                    <span class="text-2xl xl:text-3xl font-black text-primary-600 dark:text-primary-400 leading-none">
                        S/ {{ number_format($sale->total, 2) }}
                    </span>
                </div>

                <div class="flex w-full flex-col gap-3 sm:flex-row">
                    <div
                        class="flex w-full sm:w-1/2 [&>button]:w-full [&>button]:h-full [&>button]:text-base sm:[&>button]:text-lg [&>button]:shadow-md">
                        {{-- 🌟 Llamamos al nuevo Modal Moderno --}}
                        {{ $this->sendToKitchenAction }}
                    </div>

                    <div
                        class="flex w-full sm:w-1/2 [&>button]:w-full [&>button]:h-full [&>button]:text-base sm:[&>button]:text-lg [&>button]:shadow-md">
                        {{ $this->checkoutAction }}
                    </div>
                </div>
            </div>
        </div>

    </div>

    <style>
        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* 🌟 OBLIGAR LAS PROPORCIONES EXACTAS EN PC (Ignorando a Tailwind) */
        @media (min-width: 1024px) {
            .pos-panel-izquierdo {
                flex: 1 1 0% !important;
                /* Toma absolutamente todo el espacio disponible */
                width: auto !important;
            }

            .pos-panel-derecho {
                flex: 0 0 340px !important;
                /* Ancho fijo perfecto para impresoras térmicas */
                width: 340px !important;
            }
        }

        /* En celulares (menor a 1024px) ambos ocupan el 100% y se apilan */
        @media (max-width: 1023px) {

            .pos-panel-izquierdo,
            .pos-panel-derecho {
                width: 100% !important;
            }
        }

        .pos-products-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }

        @media (min-width: 640px) {
            .pos-products-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .pos-products-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (min-width: 1280px) {
            .pos-products-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }

        @media (min-width: 1536px) {
            .pos-products-grid {
                grid-template-columns: repeat(6, minmax(0, 1fr));
            }
        }

        .pos-product-card {
            min-height: 92px;
        }

        @media (min-width: 768px) {
            .pos-product-card {
                min-height: 104px;
            }
        }

        .pos-product-name {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .pos-search-input {
            padding-left: 2.75rem !important;
            padding-right: 2.75rem !important;
        }
    </style>
</x-filament-panels::page>
