@extends('storefront.layouts.app')

@section('content')
    {{-- 🌟 CATÁLOGO DE PRODUCTOS --}}
    <main class="max-w-6xl mx-auto px-4 py-6 md:py-8">

        {{-- BANNER DE BIENVENIDA --}}
        <section
            class="relative overflow-hidden rounded-3xl mb-6 border border-slate-100 shadow-sm"
            style="background:
                radial-gradient(circle at top right, color-mix(in srgb, {{ $tenant->primary_color ?? '#4f46e5' }} 28%, transparent), transparent 38%),
                linear-gradient(135deg, color-mix(in srgb, {{ $tenant->primary_color ?? '#4f46e5' }} 15%, white), #ffffff 55%, #f8fafc);"
        >
            <div class="relative p-5 md:p-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-5">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 bg-white/80 border border-white/80 rounded-full px-3 py-1 text-[11px] md:text-xs font-black text-brand shadow-sm mb-3">
                            <x-heroicon-o-sparkles class="w-4 h-4" />
                            Bienvenido a nuestra tienda online
                        </div>

                        <h2 class="text-2xl md:text-4xl font-black text-slate-950 leading-tight">
                            Explora la tienda y encuentra tus productos favoritos
                        </h2>

                        <p class="text-sm md:text-base text-slate-600 mt-2 leading-relaxed">
                            Ingresa al catálogo completo, filtra por categorías y agrega tus productos al carrito para enviar tu pedido por WhatsApp.
                        </p>

                        <div class="flex flex-wrap items-center gap-2 mt-4">
                            <span class="inline-flex items-center gap-1.5 bg-white/85 border border-white rounded-full px-3 py-1.5 text-xs font-bold text-slate-700 shadow-sm">
                                <x-heroicon-o-truck class="w-4 h-4 text-brand" />
                                Delivery
                            </span>

                            <span class="inline-flex items-center gap-1.5 bg-white/85 border border-white rounded-full px-3 py-1.5 text-xs font-bold text-slate-700 shadow-sm">
                                <x-heroicon-o-building-storefront class="w-4 h-4 text-brand" />
                                Recojo en tienda
                            </span>

                            <span class="inline-flex items-center gap-1.5 bg-white/85 border border-white rounded-full px-3 py-1.5 text-xs font-bold text-slate-700 shadow-sm">
                                <x-heroicon-o-device-phone-mobile class="w-4 h-4 text-brand" />
                                Confirmación por WhatsApp
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 mt-5">
                            <a
                                href="/productos"
                                class="inline-flex items-center gap-2 bg-brand text-white font-black px-5 py-3 rounded-2xl shadow-brand hover:opacity-90 active:scale-95 transition"
                            >
                                Ver todos los productos
                                <x-heroicon-o-arrow-right class="w-5 h-5" />
                            </a>

                            <button
                                type="button"
                                onclick="toggleCart()"
                                class="inline-flex items-center gap-2 bg-white text-slate-700 border border-slate-200 font-black px-5 py-3 rounded-2xl hover:bg-slate-50 active:scale-95 transition"
                            >
                                Ver carrito
                                <x-heroicon-o-shopping-cart class="w-5 h-5 text-brand" />
                            </button>
                        </div>
                    </div>

                    <div class="hidden md:flex w-32 h-32 rounded-[2rem] bg-white/70 border border-white shadow-sm items-center justify-center shrink-0">
                        <x-heroicon-o-shopping-bag class="w-16 h-16 text-brand" />
                    </div>
                </div>

                @if(! $tenant->is_open_for_orders)
                    <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 flex items-start gap-2">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0 mt-0.5" />
                        <div>
                            <strong>Tienda cerrada por ahora.</strong>
                            Puedes revisar el catálogo, pero los pedidos se atenderán cuando vuelva a abrir.
                        </div>
                    </div>
                @endif
            </div>
        </section>
    
        {{-- Resumen superior --}}
        <section class="mb-4">
            <div class="flex items-end justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-black text-slate-950">
                        Productos destacados
                    </h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Algunos productos disponibles en nuestra tienda.
                    </p>
                </div>

                <a
                    href="/productos"
                    class="hidden sm:inline-flex items-center gap-2 text-sm font-black text-brand hover:opacity-80 transition"
                >
                    Ver catálogo completo
                    <x-heroicon-o-arrow-right class="w-4 h-4" />
                </a>
            </div>
        </section>
        
        {{-- Estado vacío --}}
        <div id="empty-state" class="hidden bg-white rounded-3xl border border-gray-100 shadow-sm py-14 px-6 text-center mb-6">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">No encontramos productos</h3>
            <p class="text-sm text-gray-500 mt-1">Prueba con otro nombre o cambia la categoría.</p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6" id="products-grid">
            @forelse($products as $product)
                @include('storefront.partials.product-card', ['product' => $product])
            @empty
                <div class="col-span-full bg-white border border-slate-100 rounded-3xl p-10 text-center shadow-sm">
                    <div class="w-16 h-16 mx-auto rounded-full bg-slate-100 flex items-center justify-center mb-4">
                        <x-heroicon-o-inbox class="w-8 h-8 text-slate-400" />
                    </div>
                    <h3 class="text-lg font-black text-slate-800">No hay productos disponibles</h3>
                    <p class="text-sm text-slate-500 mt-1">
                        La tienda aún no tiene productos publicados.
                    </p>
                </div>
            @endforelse
        </div>
    </main>

    <script>
        @include('storefront.partials.product-filter-script')
    </script>
@endsection
