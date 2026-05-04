{{-- 🌟 FOOTER DEL NEGOCIO Y MARCA SAAS --}}
    <footer class="bg-white border-t border-gray-200 mt-12 pt-8 pb-24 shadow-inner">
        <div class="max-w-6xl mx-auto px-4 grid grid-cols-1 md:grid-cols-3 gap-8 text-center md:text-left">

            {{-- 1. Información del Negocio --}}
            <div>
                <h3 class="font-black text-gray-800 text-xl mb-3">{{ $tenant->name }}</h3>
                <div class="space-y-2 text-sm text-gray-500">
                    @if($tenant->address) <p class="flex items-center justify-center md:justify-start gap-2"><x-heroicon-o-map-pin class="w-5 h-5 text-brand" /> {{ $tenant->address }}</p> @endif
                    @if($tenant->phone) <p class="flex items-center justify-center md:justify-start gap-2"><x-heroicon-o-phone class="w-5 h-5 text-brand" /> {{ $tenant->phone }}</p> @endif
                    @if($tenant->business_hours) <p class="flex items-center justify-center md:justify-start gap-2"><x-heroicon-o-clock class="w-5 h-5 text-brand" /> {{ $tenant->business_hours }}</p> @endif
                </div>
            </div>

            {{-- 2. Enlaces Rápidos (Legal) --}}
            <div>
                <h4 class="font-bold text-gray-800 mb-3 uppercase tracking-wider text-sm">Enlaces Rápidos</h4>
                <ul class="space-y-2 text-sm text-gray-500">
                    <li><a href="#" class="hover:text-brand transition">Términos y Condiciones</a></li>
                    <li><a href="#" class="hover:text-brand transition">Políticas de Privacidad</a></li>
                    <li><a href="#" class="hover:text-brand transition">Zonas de Reparto</a></li>
                </ul>
            </div>

            {{-- 3. Métodos de Pago Seguros (Vital para E-commerce) --}}
            <div>
                <h4 class="font-bold text-gray-800 mb-3 uppercase tracking-wider text-sm">Pago 100% Seguro</h4>
                <p class="text-sm text-gray-500 mb-3">Aceptamos transferencias y billeteras digitales:</p>
                <div class="flex items-center justify-center md:justify-start gap-3">
                    {{-- YAPE --}}
                    <div class="bg-[#74006E] text-white text-xs font-black px-3 py-1.5 rounded-lg">YAPE</div>
                    {{-- PLIN --}}
                    <div class="bg-[#00D8D6] text-white text-xs font-black px-3 py-1.5 rounded-lg">PLIN</div>
                    {{-- EFECTIVO --}}
                    <div class="bg-gray-800 text-white text-xs font-bold px-3 py-1.5 rounded-lg">Efectivo</div>
                </div>
            </div>
        </div>

        {{-- 🌟 TU PUBLICIDAD SAAS --}}
        <div class="mt-12 pt-6 border-t border-gray-200 text-center flex flex-col md:flex-row items-center justify-between max-w-6xl mx-auto px-4">
            <p class="text-sm text-gray-400 mb-4 md:mb-0">© {{ date('Y') }} {{ $tenant->name }}. Todos los derechos reservados.</p>
            <a href="https://tusaas.com" target="_blank" class="inline-flex items-center gap-1 group text-gray-400 hover:text-indigo-600 transition">
                <span class="text-xs">Tecnología de</span>
                <span class="text-sm font-bold flex items-center gap-1">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> Virtual TI SaaS
                </span>
            </a>
        </div>

    </footer>
