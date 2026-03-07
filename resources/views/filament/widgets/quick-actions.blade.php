<x-filament-widgets::widget>
    <x-filament::section>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @php
                $openCash = \Percy\Core\Models\CashRegister::where('tenant_id', auth()->user()->tenant_id)
                    ->where('user_id', auth()->id())
                    ->where('status', 'open')
                    ->exists();
            @endphp

            @if(!$openCash)
                <a href="{{ route('filament.admin.resources.cash-registers.create') }}" 
                   style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);" 
                   class="flex flex-col items-center justify-center p-6 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 hover:opacity-90">
                    <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    <span class="text-base font-bold">Abrir Caja</span>
                    <span class="text-xs mt-1 opacity-90">Iniciar jornada</span>
                </a>
            @else
                <a href="{{ route('filament.admin.resources.sales.create') }}" 
                   style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);" 
                   class="flex flex-col items-center justify-center p-6 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 hover:opacity-90">
                    <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span class="text-base font-bold">Nueva Venta</span>
                    <span class="text-xs mt-1 opacity-90">Registrar comprobante</span>
                </a>
            @endif

            <a href="{{ route('filament.admin.resources.products.index') }}" 
               style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);" 
               class="flex flex-col items-center justify-center p-6 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 hover:opacity-90">
                <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <span class="text-base font-bold">Productos</span>
                <span class="text-xs mt-1 opacity-90">Gestionar inventario</span>
            </a>

            <a href="{{ route('filament.admin.resources.reports.index') }}" 
               style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);" 
               class="flex flex-col items-center justify-center p-6 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 hover:opacity-90">
                <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span class="text-base font-bold">Reportes</span>
                <span class="text-xs mt-1 opacity-90">Ver estadísticas</span>
            </a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
