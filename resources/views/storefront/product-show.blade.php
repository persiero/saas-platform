@extends('storefront.layouts.app')

@section('content')
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
            default => $codigoSunat,
        };

        $descripcion = data_get($product, 'description') ?: data_get($product, 'details');
    @endphp

    <main class="max-w-6xl mx-auto px-4 py-6 md:py-8">

        {{-- MIGAS / REGRESAR --}}
        <div class="mb-5 flex items-center justify-between gap-3">
            <a href="/productos" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-brand transition">
                <x-heroicon-o-arrow-left class="w-4 h-4" />
                Volver al catálogo
            </a>

            <a href="/" class="text-sm font-bold text-slate-500 hover:text-brand transition">
                Inicio
            </a>
        </div>

        {{-- DETALLE PRINCIPAL --}}
        <section class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-0">

                {{-- IMAGEN --}}
                <div class="bg-slate-50 p-4 md:p-6">
                    <div class="relative rounded-3xl overflow-hidden bg-white border border-slate-100 shadow-sm aspect-square flex items-center justify-center">
                        @if($product->image)
                            <img
                                src="{{ Storage::disk('r2_public')->url($product->image) }}"
                                alt="{{ $product->name }}"
                                class="w-full h-full object-cover"
                            >
                        @else
                            <div class="flex flex-col items-center justify-center text-slate-300">
                                <x-heroicon-o-photo class="w-24 h-24" />
                                <span class="text-sm font-bold mt-2">Sin imagen</span>
                            </div>
                        @endif

                        <div class="absolute top-4 right-4 bg-white/95 backdrop-blur-sm px-3 py-1.5 rounded-2xl shadow-sm border border-slate-100 text-xs font-black text-slate-700 uppercase">
                            X {{ $unidadAmigable }}
                        </div>
                    </div>
                </div>

                {{-- INFORMACIÓN --}}
                <div class="p-5 md:p-8 flex flex-col">
                    <div class="mb-4">
                        <span class="inline-flex items-center gap-1.5 bg-brand-soft text-brand px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-wider">
                            <x-heroicon-o-tag class="w-4 h-4" />
                            {{ $product->category->name ?? 'General' }}
                        </span>
                    </div>

                    <h1 class="text-2xl md:text-4xl font-black text-slate-950 leading-tight">
                        {{ $product->name }}
                    </h1>

                    <div class="mt-4 flex items-end gap-2">
                        <span class="text-3xl md:text-4xl font-black text-slate-950">
                            S/ {{ number_format($product->price, 2) }}
                        </span>
                        <span class="text-sm font-bold text-slate-400 mb-1">
                            x {{ $unidadAmigable }}
                        </span>
                    </div>

                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                            <div class="flex items-center gap-2 text-slate-500 text-sm font-bold">
                                <x-heroicon-o-check-circle class="w-5 h-5 text-emerald-500" />
                                Disponibilidad
                            </div>

                            <p class="mt-1 text-sm font-black text-slate-800">
                                {{ $product->type === 'service' ? 'Servicio disponible' : 'Producto disponible' }}
                            </p>
                        </div>

                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                            <div class="flex items-center gap-2 text-slate-500 text-sm font-bold">
                                <x-heroicon-o-truck class="w-5 h-5 text-brand" />
                                Entrega
                            </div>

                            <p class="mt-1 text-sm font-black text-slate-800">
                                Delivery o recojo
                            </p>
                        </div>
                    </div>

                    @if($descripcion)
                        <div class="mt-6">
                            <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-2">
                                Descripción
                            </h2>

                            <p class="text-sm md:text-base text-slate-600 leading-relaxed">
                                {{ $descripcion }}
                            </p>
                        </div>
                    @else
                        <div class="mt-6 rounded-2xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-500">
                            Este producto aún no tiene una descripción detallada.
                        </div>
                    @endif

                    <div class="mt-8 flex flex-col sm:flex-row gap-3">
                        <button
                            onclick="addToCart({{ $product->id }}, @js($product->name), {{ $product->price }}, @js($unidadAmigable))"
                            class="flex-1 inline-flex items-center justify-center gap-2 bg-brand text-white font-black px-5 py-3.5 rounded-2xl shadow-brand hover:opacity-90 active:scale-95 transition disabled:opacity-50 disabled:cursor-not-allowed"
                            @disabled(! $tenant->is_open_for_orders)
                        >
                            <x-heroicon-o-shopping-cart class="w-5 h-5" />
                            {{ $tenant->is_open_for_orders ? 'Agregar al carrito' : 'Tienda cerrada' }}
                        </button>

                        <button
                            type="button"
                            onclick="toggleCart()"
                            class="inline-flex items-center justify-center gap-2 bg-white text-slate-700 border border-slate-200 font-black px-5 py-3.5 rounded-2xl hover:bg-slate-50 active:scale-95 transition"
                        >
                            Ver carrito
                        </button>
                    </div>
                </div>
            </div>
        </section>

        {{-- PRODUCTOS RELACIONADOS --}}
        @if($relatedProducts->count() > 0)
            <section class="mt-10">
                <div class="flex items-end justify-between gap-3 mb-4">
                    <div>
                        <p class="text-sm font-semibold text-brand uppercase tracking-wider">
                            También te puede interesar
                        </p>

                        <h2 class="text-2xl font-black text-slate-950">
                            Productos relacionados
                        </h2>
                    </div>

                    <a href="/productos" class="hidden sm:inline-flex text-sm font-black text-brand hover:opacity-80">
                        Ver todos
                    </a>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
                    @foreach($relatedProducts as $related)
                        @include('storefront.partials.product-card', ['product' => $related])
                    @endforeach
                </div>
            </section>
        @endif
    </main>
@endsection