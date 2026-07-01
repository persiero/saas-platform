<x-filament-widgets::widget>
    @php
        $user = auth()->user();
        $tenant = $user?->tenant;

        $planService = app(\Percy\Core\Services\Tenants\TenantPlanService::class);

        $has = fn (string $feature): bool => $tenant
            ? $planService->has($feature, $tenant)
            : false;

        $routeUrl = function (string $name): ?string {
            return \Illuminate\Support\Facades\Route::has($name)
                ? route($name)
                : null;
        };

        $openCash = false;

        if ($user && $user->tenant_id && $has('has_cash_register')) {
            $openCash = \Percy\Core\Models\CashRegister::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', auth()->id())
                ->where('status', 'open')
                ->exists();
        }

        $cards = [];

        if ($has('has_cash_register') && ! $openCash && $user?->canOpenCashRegister()) {
            $url = $routeUrl('filament.admin.resources.cash-registers.index');

            if ($url) {
                $cards[] = [
                    'title' => 'Abrir Caja',
                    'subtitle' => 'Iniciar jornada',
                    'url' => $url,
                    'icon' => 'heroicon-o-lock-open',
                    'style' => 'background: linear-gradient(135deg, #10b981 0%, #047857 100%);',
                ];
            }
        }

        if ($has('has_internal_sales') && $openCash) {
            $url = $routeUrl('filament.admin.resources.sales.create');

            if ($url) {
                $cards[] = [
                    'title' => 'Nueva Venta',
                    'subtitle' => $has('has_sunat') ? 'Emitir comprobante' : 'Ticket interno',
                    'url' => $url,
                    'icon' => 'heroicon-o-shopping-cart',
                    'style' => 'background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);',
                ];
            }
        }

        if ($has('has_basic_inventory') && ($user?->canViewProducts() ?? false)) {
            $url = $routeUrl('filament.admin.resources.products.index');

            if ($url) {
                $cards[] = [
                    'title' => 'Productos',
                    'subtitle' => 'Inventario y stock',
                    'url' => $url,
                    'icon' => 'heroicon-o-cube',
                    'style' => 'background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%);',
                ];
            }
        }

        if ($has('has_clients')) {
            $url = $routeUrl('filament.admin.resources.customers.index');

            if ($url) {
                $cards[] = [
                    'title' => 'Clientes',
                    'subtitle' => 'Directorio',
                    'url' => $url,
                    'icon' => 'heroicon-o-users',
                    'style' => 'background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);',
                ];
            }
        }

        if ($has('has_purchases') && ($user?->isAdmin() ?? false)) {
            $url = $routeUrl('filament.admin.resources.purchases.index');

            if ($url) {
                $cards[] = [
                    'title' => 'Compras',
                    'subtitle' => 'Reposición de stock',
                    'url' => $url,
                    'icon' => 'heroicon-o-truck',
                    'style' => 'background: linear-gradient(135deg, #14b8a6 0%, #0f766e 100%);',
                ];
            }
        }

        if (($has('has_basic_reports') || $has('has_advanced_reports')) && ($user?->isAdmin() ?? false)) {
            $url = $routeUrl('filament.admin.resources.reports.index');

            if ($url) {
                $cards[] = [
                    'title' => 'Reportes',
                    'subtitle' => $has('has_advanced_reports') ? 'Análisis avanzado' : 'Resumen del negocio',
                    'url' => $url,
                    'icon' => 'heroicon-o-chart-bar',
                    'style' => 'background: linear-gradient(135deg, #06b6d4 0%, #0e7490 100%);',
                ];
            }
        }

        if (($has('has_online_store') || $has('has_delivery')) && ($user?->isAdmin() ?? false)) {
            $url = $routeUrl('filament.admin.resources.delivery-zones.index');

            if ($url) {
                $cards[] = [
                    'title' => 'Delivery',
                    'subtitle' => 'Zonas de reparto',
                    'url' => $url,
                    'icon' => 'heroicon-o-map-pin',
                    'style' => 'background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);',
                ];
            }
        }
    @endphp

    <div style="margin-bottom: 0.75rem;">
        <h2 style="font-size: 1rem; font-weight: 700; color: #111827;">
            Accesos rápidos
        </h2>
        <p style="font-size: 0.875rem; color: #6b7280;">
            Operaciones frecuentes según tu plan contratado.
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem;">
        @foreach ($cards as $card)
            <a href="{{ $card['url'] }}"
               style="{{ $card['style'] }} color: white; padding: 1.25rem; border-radius: 1rem; display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; box-shadow: 0 4px 8px rgba(0,0,0,0.10); transition: transform 0.2s ease, box-shadow 0.2s ease; min-height: 120px;"
               onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 16px rgba(0,0,0,0.16)'"
               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.10)'">

                <x-dynamic-component
                    :component="$card['icon']"
                    style="width: 2.25rem; height: 2.25rem; margin-bottom: 0.5rem; color: white;"
                />

                <span style="font-size: 1.05rem; font-weight: 700; color: white;">
                    {{ $card['title'] }}
                </span>

                <span style="font-size: 0.75rem; opacity: 0.9; color: white; margin-top: 0.25rem;">
                    {{ $card['subtitle'] }}
                </span>
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>
