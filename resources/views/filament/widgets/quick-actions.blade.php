<x-filament-widgets::widget>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem;">

        @php
            $openCash = \Percy\Core\Models\CashRegister::where('tenant_id', auth()->user()->tenant_id)
                ->where('user_id', auth()->id())
                ->where('status', 'open')
                ->exists();
        @endphp

        {{-- 1. BOTÓN DINÁMICO: CAJA O VENTA --}}
        @if(!$openCash)
            <a href="{{ route('filament.admin.resources.cash-registers.index') }}"
               style="background: linear-gradient(135deg, #10b981 0%, #047857 100%); color: white; padding: 1.5rem; border-radius: 1rem; display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s ease;"
               onmouseover="this.style.transform='translateY(-5px)'"
               onmouseout="this.style.transform='translateY(0)'">
                <x-heroicon-o-lock-open style="width: 2.5rem; height: 2.5rem; margin-bottom: 0.5rem; color: white;" />
                <span style="font-size: 1.125rem; font-weight: bold; color: white;">Abrir Caja</span>
                <span style="font-size: 0.75rem; opacity: 0.9; color: white; margin-top: 0.25rem;">Iniciar jornada</span>
            </a>
        @else
            <a href="{{ route('filament.admin.resources.sales.create') }}"
               style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: white; padding: 1.5rem; border-radius: 1rem; display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s ease;"
               onmouseover="this.style.transform='translateY(-5px)'"
               onmouseout="this.style.transform='translateY(0)'">
                <x-heroicon-o-shopping-cart style="width: 2.5rem; height: 2.5rem; margin-bottom: 0.5rem; color: white;" />
                <span style="font-size: 1.125rem; font-weight: bold; color: white;">Nueva Venta</span>
                <span style="font-size: 0.75rem; opacity: 0.9; color: white; margin-top: 0.25rem;">Facturar ahora</span>
            </a>
        @endif

        {{-- 2. BOTÓN DE PRODUCTOS --}}
        <a href="{{ route('filament.admin.resources.products.index') }}"
           style="background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%); color: white; padding: 1.5rem; border-radius: 1rem; display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s ease;"
           onmouseover="this.style.transform='translateY(-5px)'"
           onmouseout="this.style.transform='translateY(0)'">
            <x-heroicon-o-cube style="width: 2.5rem; height: 2.5rem; margin-bottom: 0.5rem; color: white;" />
            <span style="font-size: 1.125rem; font-weight: bold; color: white;">Productos</span>
            <span style="font-size: 0.75rem; opacity: 0.9; color: white; margin-top: 0.25rem;">Inventario y Stock</span>
        </a>

        {{-- 3. BOTÓN DE CLIENTES --}}
        <a href="{{ route('filament.admin.resources.customers.index') }}"
           style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); color: white; padding: 1.5rem; border-radius: 1rem; display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s ease;"
           onmouseover="this.style.transform='translateY(-5px)'"
           onmouseout="this.style.transform='translateY(0)'">
            <x-heroicon-o-users style="width: 2.5rem; height: 2.5rem; margin-bottom: 0.5rem; color: white;" />
            <span style="font-size: 1.125rem; font-weight: bold; color: white;">Clientes</span>
            <span style="font-size: 0.75rem; opacity: 0.9; color: white; margin-top: 0.25rem;">Directorio</span>
        </a>

        {{-- 4. BOTÓN DE REPORTES --}}
        @if(auth()->user()->isAdmin())
            <a href="{{ route('filament.admin.resources.reports.index') }}"
            style="background: linear-gradient(135deg, #06b6d4 0%, #0e7490 100%); color: white; padding: 1.5rem; border-radius: 1rem; display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s ease;"
            onmouseover="this.style.transform='translateY(-5px)'"
            onmouseout="this.style.transform='translateY(0)'">
                <x-heroicon-o-chart-bar style="width: 2.5rem; height: 2.5rem; margin-bottom: 0.5rem; color: white;" />
                <span style="font-size: 1.125rem; font-weight: bold; color: white;">Reportes</span>
                <span style="font-size: 0.75rem; opacity: 0.9; color: white; margin-top: 0.25rem;">Ver finanzas</span>
            </a>
        @endif

    </div>
</x-filament-widgets::widget>
