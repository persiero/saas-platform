<x-filament-panels::page>
    <form wire:submit="loadReports" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{ $this->form }}
        </div>
        
        <x-filament::button type="submit" class="mt-4">
            Generar Reportes
        </x-filament::button>
    </form>

    <div class="mt-8 space-y-6">
        <!-- Resumen de Ventas -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-filament::card>
                <div class="text-center">
                    <div class="text-sm text-gray-500">Total Ventas</div>
                    <div class="text-2xl font-bold text-success-600">S/ {{ number_format($salesData['total_sales'] ?? 0, 2) }}</div>
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-center">
                    <div class="text-sm text-gray-500">N° Comprobantes</div>
                    <div class="text-2xl font-bold">{{ $salesData['total_count'] ?? 0 }}</div>
                </div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-center">
                    <div class="text-sm text-gray-500">Ticket Promedio</div>
                    <div class="text-2xl font-bold">S/ {{ number_format(($salesData['total_count'] ?? 0) > 0 ? ($salesData['total_sales'] ?? 0) / $salesData['total_count'] : 0, 2) }}</div>
                </div>
            </x-filament::card>
        </div>

        <!-- Rentabilidad -->
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-4">Rentabilidad</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <div class="text-sm text-gray-500">Ingresos</div>
                    <div class="text-xl font-bold text-success-600">S/ {{ number_format($profitability['sales'] ?? 0, 2) }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Gastos</div>
                    <div class="text-xl font-bold text-danger-600">S/ {{ number_format($profitability['expenses'] ?? 0, 2) }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Utilidad</div>
                    <div class="text-xl font-bold {{ ($profitability['profit'] ?? 0) >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                        S/ {{ number_format($profitability['profit'] ?? 0, 2) }}
                    </div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Margen</div>
                    <div class="text-xl font-bold">{{ number_format($profitability['margin_percentage'] ?? 0, 2) }}%</div>
                </div>
            </div>
        </x-filament::card>

        <!-- Productos Más Vendidos -->
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-4">Top 10 Productos Más Vendidos</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2">Producto</th>
                            <th class="text-right py-2">Cantidad</th>
                            <th class="text-right py-2">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topProducts as $product)
                            <tr class="border-b">
                                <td class="py-2">{{ $product['name'] }}</td>
                                <td class="text-right">{{ number_format($product['total_quantity'], 0) }}</td>
                                <td class="text-right">S/ {{ number_format($product['total_amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center py-4 text-gray-500">No hay datos</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::card>

        <!-- Estado de Caja -->
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-4">Estado de Caja</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="text-sm text-gray-500">Cajas Abiertas</div>
                    <div class="text-xl font-bold">{{ $cashStatus['open_registers'] ?? 0 }}</div>
                    <div class="text-sm text-gray-600">Total: S/ {{ number_format($cashStatus['total_open_amount'] ?? 0, 2) }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Cerradas Hoy</div>
                    <div class="text-xl font-bold">{{ $cashStatus['today_closed'] ?? 0 }}</div>
                    <div class="text-sm text-gray-600">Total: S/ {{ number_format($cashStatus['today_closed_amount'] ?? 0, 2) }}</div>
                </div>
            </div>
        </x-filament::card>
    </div>
</x-filament-panels::page>
