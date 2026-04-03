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

                <div class="product-card bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-col justify-between"
                     data-name="{{ strtolower($product->name) }}"
                     data-category="{{ strtolower($product->category->name ?? 'General') }}">

                    @if($product->image)
                        <img src="{{ Storage::disk('r2_public')->url($product->image) }}" alt="{{ $product->name }}" class="w-full h-40 object-cover rounded-xl mb-3 border border-gray-100 shadow-sm">
                    @else
                        <div class="w-full h-40 bg-gray-50 rounded-xl mb-3 flex items-center justify-center text-gray-300 border border-gray-100">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                    @endif

                    <div>
                        <h3 class="text-sm font-bold text-gray-800 leading-tight">{{ $product->name }}</h3>
                        <p class="text-xs text-gray-500 mt-1">{{ $product->category->name ?? 'General' }}</p>
                    </div>

                    <div class="mt-3 flex items-center justify-between">
                        <div class="flex flex-col">
                            <span class="font-black text-brand leading-none">S/ {{ number_format($product->price, 2) }}</span>
                            {{-- 🌟 IMPRIMIMOS LA UNIDAD AMIGABLE AQUÍ --}}
                            <span class="text-[10px] text-gray-400 font-bold mt-1 uppercase">x {{ $unidadAmigable }}</span>
                        </div>

                        {{-- 🌟 ENVIAMOS LA UNIDAD AMIGABLE AL CARRITO (JS) --}}
                        <button onclick="addToCart('{{ addslashes($product->name) }}', {{ $product->price }}, '{{ $unidadAmigable }}')" class="bg-brand text-white w-8 h-8 rounded-lg flex items-center justify-center hover:opacity-90 transition font-bold shadow-sm">
                            +
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </main>
@endsection
