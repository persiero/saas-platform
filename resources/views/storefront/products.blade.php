@extends('storefront.layouts.app')

@section('content')
    <main class="max-w-6xl mx-auto px-4 py-6 md:py-8">

        {{-- ENCABEZADO COMPACTO --}}
        <section class="mb-4">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-xs md:text-sm font-black text-brand uppercase tracking-wider">
                    Catálogo completo
                </span>

                <span class="text-slate-300 font-bold">|</span>

                <h2 class="text-xl md:text-2xl font-black text-slate-950 leading-none">
                    Todos los productos
                </h2>
            </div>
        </section>

        {{-- FILTROS COMPLEMENTARIOS --}}
        <section class="mb-6">
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-3 md:p-4">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 mb-3">
                    <div>
                        <h3 class="text-sm font-black text-slate-900">
                            Filtros del catálogo
                        </h3>
                    </div>

                    <div class="inline-flex items-center gap-2 bg-slate-50 border border-slate-100 rounded-2xl px-3 py-2 text-xs md:text-sm font-bold text-slate-600 w-fit">
                        <x-heroicon-o-shopping-bag class="w-4 h-4 text-brand" />
                        <span id="products-count-label">
                            {{ $products->count() }} producto{{ $products->count() !== 1 ? 's' : '' }} encontrado{{ $products->count() !== 1 ? 's' : '' }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
                    {{-- Ordenar --}}
                    <div>
                        <label class="block text-xs font-black text-slate-600 mb-1.5">
                            Ordenar por
                        </label>

                        <select
                            id="sortSelect"
                            onchange="applyFilters()"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 focus-brand outline-none"
                        >
                            <option value="default">Destacados</option>
                            <option value="name_asc">Nombre A-Z</option>
                            <option value="name_desc">Nombre Z-A</option>
                            <option value="price_asc">Precio menor a mayor</option>
                            <option value="price_desc">Precio mayor a menor</option>
                        </select>
                    </div>

                    {{-- Precio mínimo --}}
                    <div>
                        <label class="block text-xs font-black text-slate-600 mb-1.5">
                            Precio mínimo
                        </label>

                        <input
                            type="number"
                            id="minPrice"
                            oninput="applyFilters()"
                            placeholder="S/ 0.00"
                            min="0"
                            step="0.10"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 focus-brand outline-none"
                        >
                    </div>

                    {{-- Precio máximo --}}
                    <div>
                        <label class="block text-xs font-black text-slate-600 mb-1.5">
                            Precio máximo
                        </label>

                        <input
                            type="number"
                            id="maxPrice"
                            oninput="applyFilters()"
                            placeholder="S/ 100.00"
                            min="0"
                            step="0.10"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 focus-brand outline-none"
                        >
                    </div>

                    {{-- Unidad --}}
                    <div>
                        <label class="block text-xs font-black text-slate-600 mb-1.5">
                            Unidad
                        </label>

                        <select
                            id="unitSelect"
                            onchange="applyFilters()"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 focus-brand outline-none"
                        >
                            <option value="all">Todas</option>
                            <option value="Und.">Unidad</option>
                            <option value="Kg.">Kilogramo</option>
                            <option value="Lt.">Litro</option>
                            <option value="Galón">Galón</option>
                            <option value="g.">Gramo</option>
                            <option value="Caja">Caja</option>
                            <option value="Paquete">Paquete</option>
                        </select>
                    </div>

                    {{-- Disponible + limpiar --}}
                    <div class="flex flex-col justify-end gap-2">
                        <label class="inline-flex items-center gap-2 text-sm font-bold text-slate-700 bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3">
                            <input
                                type="checkbox"
                                id="availableOnly"
                                onchange="applyFilters()"
                                class="rounded border-slate-300 text-brand focus:ring-0"
                            >
                            Solo disponibles
                        </label>

                        <button
                            type="button"
                            onclick="resetFilters()"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 hover:bg-slate-50 transition"
                        >
                            Limpiar filtros
                        </button>
                    </div>
                </div>
            </div>
        </section>
        
        {{-- ESTADO VACÍO POR FILTROS --}}
        <div id="empty-state" class="hidden bg-white rounded-3xl border border-slate-100 shadow-sm py-14 px-6 text-center mb-6">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-slate-100 flex items-center justify-center">
                <x-heroicon-o-magnifying-glass class="w-8 h-8 text-slate-400" />
            </div>

            <h3 class="text-lg font-black text-slate-800">
                No encontramos productos
            </h3>

            <p class="text-sm text-slate-500 mt-1">
                Prueba con otro nombre o cambia la categoría.
            </p>
        </div>

        {{-- GRID DE PRODUCTOS --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6" id="products-grid">
            @forelse($products as $product)
                @include('storefront.partials.product-card', ['product' => $product])
            @empty
                <div class="col-span-full bg-white border border-slate-100 rounded-3xl p-10 text-center shadow-sm">
                    <div class="w-16 h-16 mx-auto rounded-full bg-slate-100 flex items-center justify-center mb-4">
                        <x-heroicon-o-inbox class="w-8 h-8 text-slate-400" />
                    </div>

                    <h3 class="text-lg font-black text-slate-800">
                        No hay productos disponibles
                    </h3>

                    <p class="text-sm text-slate-500 mt-1">
                        La tienda aún no tiene productos publicados.
                    </p>
                </div>
            @endforelse
        </div>
    </main>

    @include('storefront.partials.product-filter-script')
@endsection