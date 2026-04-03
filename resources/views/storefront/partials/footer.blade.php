{{-- 🌟 FOOTER DEL NEGOCIO Y MARCA SAAS --}}
    <footer class="bg-white border-t border-gray-200 mt-12 pt-8 pb-24 shadow-inner">
        <div class="max-w-6xl mx-auto px-4 text-center">

            {{-- Datos de la Tienda --}}
            <h3 class="font-black text-gray-800 text-lg">{{ $tenant->name }}</h3>

            <div class="mt-3 space-y-1">
                @if($tenant->address)
                    <p class="text-sm text-gray-500 flex items-center justify-center gap-1">
                        <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        {{ $tenant->address }}
                    </p>
                @endif

                @if($tenant->phone)
                    <p class="text-sm text-gray-500 flex items-center justify-center gap-1">
                        <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                        {{ $tenant->phone }}
                    </p>
                @endif

                @if($tenant->business_hours)
                    <p class="text-sm text-gray-500 flex items-center justify-center gap-1">
                        <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        {{ $tenant->business_hours }}
                    </p>
                @endif
            </div>

            {{-- 🌟 TU PUBLICIDAD SAAS (El gancho para conseguir más clientes) --}}
            <div class="mt-8 pt-6 border-t border-gray-100">
                <a href="https://tusaas.com" target="_blank" class="inline-flex flex-col items-center group">
                    <span class="text-xs text-gray-400 mb-1 group-hover:text-gray-600 transition">Impulsado por</span>
                    <span class="text-sm font-bold text-gray-300 group-hover:text-indigo-600 transition flex items-center gap-1">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Virtual TI SaaS
                    </span>
                </a>
            </div>

        </div>
    </footer>
