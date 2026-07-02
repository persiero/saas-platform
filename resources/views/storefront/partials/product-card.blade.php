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
@endphp

@php
    $productUrl = '/productos/' . $product->id;
@endphp

<div
    class="product-card bg-white rounded-3xl shadow-sm border border-slate-100 p-2.5 sm:p-3 flex flex-col h-full transition-all duration-300 hover:shadow-xl hover:-translate-y-1 relative group overflow-hidden"
    data-name="{{ strtolower($product->name) }}"
    data-category="{{ strtolower($product->category->name ?? 'General') }}"
    data-price="{{ $product->price }}"
    data-unit="{{ $unidadAmigable }}"
    data-stock="{{ $product->type === 'service' ? 999999 : (float) $product->current_stock }}"
>
    <a href="{{ $productUrl }}" class="relative w-full h-36 sm:h-48 rounded-2xl mb-3 overflow-hidden bg-slate-100 flex items-center justify-center">
        @if($product->image)
            <img
                src="{{ Storage::disk('r2_public')->url($product->image) }}"
                alt="{{ $product->name }}"
                class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
            >
        @else
            <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
        @endif

        <div class="absolute top-2 right-2 bg-white/95 backdrop-blur-sm px-2.5 py-1 rounded-xl shadow-sm border border-slate-100 text-[9px] sm:text-[10px] font-black text-slate-700 uppercase tracking-wider">
            X {{ $unidadAmigable }}
        </div>
    </a>

    <div class="px-1 flex-grow flex flex-col">
        <p class="text-[10px] sm:text-xs font-semibold text-brand mb-1 uppercase tracking-wider line-clamp-1">
            {{ $product->category->name ?? 'General' }}
        </p>

        <a href="{{ $productUrl }}" class="group/title">
            <h3 class="text-xs sm:text-sm font-bold text-slate-800 group-hover/title:text-brand leading-tight line-clamp-2 mb-2 min-h-[2rem] sm:min-h-[2.5rem] transition">
                {{ $product->name }}
            </h3>
        </a>
    </div>

    <div class="px-1 mt-auto pt-2 flex flex-col min-[400px]:flex-row items-start min-[400px]:items-end justify-between border-t border-slate-100 gap-2">
        <div class="flex flex-col w-full min-[400px]:w-auto">
            <span class="font-black text-sm sm:text-lg text-slate-900 leading-none truncate w-full">
                S/ {{ number_format($product->price, 2) }}
            </span>
        </div>

        <button
            onclick="addToCart({{ $product->id }}, @js($product->name), {{ $product->price }}, @js($unidadAmigable))"
            class="w-full min-[400px]:w-auto bg-brand text-white text-xs sm:text-sm px-3 py-2 sm:px-4 sm:py-2.5 rounded-2xl flex items-center justify-center gap-1.5 hover:opacity-90 transition font-black shadow-brand active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
            @disabled(! $tenant->is_open_for_orders)
        >
            <x-heroicon-o-plus class="w-4 h-4" />
            {{ $tenant->is_open_for_orders ? 'Agregar' : 'Cerrado' }}
        </button>
    </div>
</div>