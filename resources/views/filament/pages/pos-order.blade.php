<x-filament-panels::page>
    {{-- 🌟 CONTENEDOR PRINCIPAL: Volvemos a items-start para que el ticket flote bien --}}
    <div class="flex flex-col lg:flex-row gap-6 items-start w-full">

        {{-- 🌟 PANEL IZQUIERDO: PRODUCTOS (w-3/4 asegura el 75% del monitor exacto) --}}
        <div class="pos-panel-izquierdo flex flex-col gap-4">

            {{-- Encabezado con datos de la mesa --}}
            <div class="bg-white dark:bg-gray-900 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-black text-gray-800 dark:text-gray-100">
                        {{ $sale->table->name ?? 'Mesa' }}
                        <span class="text-sm font-normal text-gray-500 ml-2">({{ $sale->table->zone->name ?? 'Zona' }})</span>
                    </h1>
                    <p class="text-xs text-gray-500">Comanda: {{ $sale->series }}-{{ $sale->correlative }}</p>
                </div>
                <x-filament::button href="{{ route('filament.admin.pages.pos-restaurant') }}" tag="a" color="gray" icon="heroicon-m-arrow-left">
                    Volver a mesas
                </x-filament::button>
            </div>

            {{-- Categorías --}}
            <div class="flex overflow-x-auto gap-2 pb-2 hide-scrollbar">
                <button
                    wire:click="setCategory(null)"
                    class="whitespace-nowrap px-6 py-2 rounded-full font-bold text-sm transition-colors shadow-sm {{ is_null($selectedCategoryId) ? 'bg-primary-600 text-white border-transparent' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800' }}"
                >
                    Todos
                </button>
                @foreach($this->categories as $category)
                    <button
                        wire:click="setCategory({{ $category->id }})"
                        class="whitespace-nowrap px-6 py-2 rounded-full font-bold text-sm transition-colors shadow-sm {{ $selectedCategoryId === $category->id ? 'bg-primary-600 text-white border-transparent' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                    >
                        {{ $category->name }}
                    </button>
                @endforeach
            </div>

            {{-- GRILLA DE PLATOS --}}
            <div class="flex-1 min-h-[50vh] overflow-y-auto bg-gray-50 dark:bg-gray-900 rounded-xl p-4 border border-gray-200 dark:border-gray-800 shadow-inner">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                    @forelse($this->products as $product)
                        <button
                            wire:click="addProduct({{ $product->id }})"
                            class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col items-center justify-center text-center hover:border-primary-500 dark:hover:border-primary-400 h-32 cursor-pointer transition group"
                        >
                            <span class="font-bold text-sm line-clamp-2 text-gray-800 dark:text-gray-200 leading-tight group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">{{ $product->name }}</span>
                            <span class="text-primary-600 dark:text-primary-400 font-black mt-2">S/ {{ number_format($product->price, 2) }}</span>
                        </button>
                    @empty
                        <div class="col-span-full text-center text-gray-500 py-12 font-medium">
                            No hay productos en esta categoría.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- 🌟 PANEL DERECHO: TICKET (w-1/4 asegura el 25% del monitor exacto) --}}
        <div class="pos-panel-derecho bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 flex flex-col sticky top-6 lg:min-w-[280px]">

            <div class="p-4 bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 rounded-t-xl">
                <h2 class="font-black text-lg text-gray-800 dark:text-white uppercase tracking-wide">Comanda</h2>
            </div>

            {{-- Lista de items --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-4 max-h-[50vh] lg:max-h-[calc(100vh-24rem)]">
                @forelse($sale->items as $item)
                    <div class="flex justify-between items-start pb-4 border-b border-gray-100 dark:border-gray-800 last:border-0 last:pb-0">

                        {{-- 🌟 COLUMNA IZQUIERDA (Nombre, Notas y Controles) --}}
                        <div class="flex-1 pr-2">
                            <h3 class="font-bold text-sm text-gray-800 dark:text-gray-200 leading-tight">
                                {{ $item->item_name }}
                            </h3>

                            {{-- 🌟 MAGIA UX: Botón de nota y visualización --}}
                            <div class="mt-1">
                                @if($item->note)
                                    <p class="text-xs text-red-500 dark:text-red-400 italic font-medium leading-tight mb-1">
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
                                <button wire:click="decrementItem({{ $item->id }})" class="w-7 h-7 flex items-center justify-center bg-gray-200 dark:bg-gray-800 rounded-full text-gray-800 dark:text-gray-200 font-black hover:bg-gray-300 dark:hover:bg-gray-700 transition cursor-pointer">
                                    -
                                </button>
                                <span class="text-sm font-bold w-6 text-center text-gray-800 dark:text-gray-200">{{ floatval($item->quantity) }}</span>
                                <button wire:click="incrementItem({{ $item->id }})" class="w-7 h-7 flex items-center justify-center bg-gray-200 dark:bg-gray-800 rounded-full text-gray-800 dark:text-gray-200 font-black hover:bg-gray-300 dark:hover:bg-gray-700 transition cursor-pointer">
                                    +
                                </button>
                            </div>
                        </div>

                        {{-- COLUMNA DERECHA (Precio y Quitar) --}}
                        <div class="text-right flex flex-col items-end">
                            <span class="font-black text-sm text-gray-800 dark:text-gray-200">S/ {{ number_format($item->total, 2) }}</span>
                            <button wire:click="removeItem({{ $item->id }})" class="text-xs text-red-500 hover:text-red-700 dark:hover:text-red-400 mt-2 font-bold flex items-center gap-1 cursor-pointer">
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

                <div class="flex justify-between items-end bg-white dark:bg-gray-900 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 mb-4">
                    <span class="text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">Total</span>
                    <span class="text-2xl xl:text-3xl font-black text-primary-600 dark:text-primary-400 leading-none">
                        S/ {{ number_format($sale->total, 2) }}
                    </span>
                </div>

                <div class="flex flex-row gap-3 w-full">
                    <div class="w-1/2">
                        <x-filament::button wire:click="sendToKitchen" color="warning" size="lg" icon="heroicon-m-fire" class="w-full h-full text-lg shadow-md">
                            A Cocina
                        </x-filament::button>
                    </div>

                    <div class="w-1/2 flex [&>button]:w-full [&>button]:h-full [&>button]:text-lg [&>button]:shadow-md">
                        {{ $this->checkoutAction }}
                    </div>
                </div>
            </div>
        </div>

    </div>

    <style>
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* 🌟 OBLIGAR LAS PROPORCIONES EXACTAS EN PC (Ignorando a Tailwind) */
        @media (min-width: 1024px) {
            .pos-panel-izquierdo {
                flex: 1 1 0% !important; /* Toma absolutamente todo el espacio disponible */
                width: auto !important;
            }
            .pos-panel-derecho {
                flex: 0 0 340px !important; /* Ancho fijo perfecto para impresoras térmicas */
                width: 340px !important;
            }
        }

        /* En celulares (menor a 1024px) ambos ocupan el 100% y se apilan */
        @media (max-width: 1023px) {
            .pos-panel-izquierdo, .pos-panel-derecho {
                width: 100% !important;
            }
        }
    </style>
</x-filament-panels::page>
