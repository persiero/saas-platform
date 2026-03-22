<x-filament-panels::page>
    {{-- Filtros de Fecha --}}
    <div class="mb-6">
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-funnel class="w-5 h-5 text-gray-500" />
                    <span class="text-lg font-semibold">Filtros de Período</span>
                </div>
            </x-slot>
            <x-slot name="description">
                Selecciona el rango de fechas para generar los reportes
            </x-slot>

            <form wire:submit="loadReports" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{ $this->form }}
                </div>

                <div class="flex gap-3">
                    <x-filament::button type="submit" icon="heroicon-o-arrow-path" color="primary">
                        Actualizar Reportes
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        color="gray"
                        icon="heroicon-o-calendar"
                        wire:click="$set('startDate', '{{ now()->startOfMonth()->format('Y-m-d') }}'); $set('endDate', '{{ now()->format('Y-m-d') }}'); loadReports()"
                    >
                        Este Mes
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        color="gray"
                        icon="heroicon-o-calendar-days"
                        wire:click="$set('startDate', '{{ now()->subDays(7)->format('Y-m-d') }}'); $set('endDate', '{{ now()->format('Y-m-d') }}'); loadReports()"
                    >
                        Últimos 7 Días
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    </div>

    <div class="space-y-6">
        {{-- Resumen de Ventas --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-currency-dollar class="w-5 h-5 text-success-500" />
                    <span class="text-lg font-semibold">Resumen de Ventas</span>
                </div>
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-gradient-to-br from-success-50 to-success-100 dark:from-success-900/20 dark:to-success-800/20 rounded-lg p-6 border border-success-200 dark:border-success-800">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm font-medium text-success-700 dark:text-success-300">Total Ventas</div>
                        <x-heroicon-o-banknotes class="w-6 h-6 text-success-600" />
                    </div>
                    <div class="text-3xl font-bold text-success-900 dark:text-success-100">S/ {{ number_format($salesData['total_sales'] ?? 0, 2) }}</div>
                    <div class="text-xs text-success-600 dark:text-success-400 mt-1">Ingresos del período</div>
                </div>

                <div class="bg-gradient-to-br from-primary-50 to-primary-100 dark:from-primary-900/20 dark:to-primary-800/20 rounded-lg p-6 border border-primary-200 dark:border-primary-800">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm font-medium text-primary-700 dark:text-primary-300">N° Comprobantes</div>
                        <x-heroicon-o-document-text class="w-6 h-6 text-primary-600" />
                    </div>
                    <div class="text-3xl font-bold text-primary-900 dark:text-primary-100">{{ $salesData['total_count'] ?? 0 }}</div>
                    <div class="text-xs text-primary-600 dark:text-primary-400 mt-1">Documentos emitidos</div>
                </div>

                <div class="bg-gradient-to-br from-warning-50 to-warning-100 dark:from-warning-900/20 dark:to-warning-800/20 rounded-lg p-6 border border-warning-200 dark:border-warning-800">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm font-medium text-warning-700 dark:text-warning-300">Ticket Promedio</div>
                        <x-heroicon-o-calculator class="w-6 h-6 text-warning-600" />
                    </div>
                    <div class="text-3xl font-bold text-warning-900 dark:text-warning-100">S/ {{ number_format(($salesData['total_count'] ?? 0) > 0 ? ($salesData['total_sales'] ?? 0) / $salesData['total_count'] : 0, 2) }}</div>
                    <div class="text-xs text-warning-600 dark:text-warning-400 mt-1">Promedio por venta</div>
                </div>
            </div>
        </x-filament::section>

        {{-- Rentabilidad --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-chart-pie class="w-5 h-5 text-primary-500" />
                    <span class="text-lg font-semibold">Análisis de Rentabilidad</span>
                </div>
            </x-slot>
            <x-slot name="description">
                Comparativa de ingresos vs gastos del período seleccionado
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- FILA 1: La operación de mercadería --}}
                {{-- Ingresos --}}
                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center justify-center gap-2 text-gray-500 dark:text-gray-400 mb-2">
                        <x-heroicon-m-arrow-trending-up class="w-5 h-5" />
                        <span class="text-sm font-medium">Ingresos Totales</span>
                    </div>
                    <div class="text-2xl font-bold text-center">S/ {{ number_format($profitability['sales'] ?? 0, 2) }}</div>
                </div>

                {{-- Costo de Ventas --}}
                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center justify-center gap-2 text-orange-500 mb-2">
                        <x-heroicon-m-shopping-cart class="w-5 h-5" />
                        <span class="text-sm font-medium">Costo de Mercadería</span>
                    </div>
                    <div class="text-2xl font-bold text-center">S/ {{ number_format($profitability['cogs'] ?? 0, 2) }}</div>
                </div>

                {{-- Utilidad Bruta (NUEVA) --}}
                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm bg-blue-50/30">
                    <div class="flex items-center justify-center gap-2 text-blue-600 mb-2">
                        <x-heroicon-m-calculator class="w-5 h-5" />
                        <span class="text-sm font-medium">Utilidad Bruta</span>
                    </div>
                    <div class="text-2xl font-bold text-center text-blue-700">S/ {{ number_format($profitability['gross_profit'] ?? 0, 2) }}</div>
                </div>

                {{-- FILA 2: La utilidad final --}}
                {{-- Gastos --}}
                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center justify-center gap-2 text-danger-500 mb-2">
                        <x-heroicon-m-arrow-trending-down class="w-5 h-5" />
                        <span class="text-sm font-medium">Gastos Operativos</span>
                    </div>
                    <div class="text-2xl font-bold text-center text-danger-600">S/ {{ number_format($profitability['expenses'] ?? 0, 2) }}</div>
                </div>

                {{-- Utilidad Neta --}}
                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm bg-success-50/30">
                    <div class="flex items-center justify-center gap-2 text-success-600 mb-2">
                        <x-heroicon-m-banknotes class="w-5 h-5" />
                        <span class="text-sm font-medium">Utilidad Neta (Bolsillo)</span>
                    </div>
                    <div class="text-2xl font-bold text-center text-success-700">S/ {{ number_format($profitability['profit'] ?? 0, 2) }}</div>
                </div>

                {{-- Margen --}}
                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center justify-center gap-2 text-gray-500 mb-2">
                        <x-heroicon-m-presentation-chart-line class="w-5 h-5" />
                        <span class="text-sm font-medium">Margen de Ganancia</span>
                    </div>
                    <div class="text-2xl font-bold text-center">{{ $profitability['margin_percentage'] ?? 0 }}%</div>
                </div>
            </div>
        </x-filament::section>

        {{-- Productos Más Vendidos --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-trophy class="w-5 h-5 text-warning-500" />
                    <span class="text-lg font-semibold">Top 10 Productos Más Vendidos</span>
                </div>
            </x-slot>
            <x-slot name="description">
                Productos con mayor volumen de ventas en el período
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-200">
                                <div class="flex items-center gap-2">
                                    <x-heroicon-o-cube class="w-4 h-4" />
                                    Producto
                                </div>
                            </th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-200">
                                <div class="flex items-center justify-end gap-2">
                                    <x-heroicon-o-hashtag class="w-4 h-4" />
                                    Cantidad
                                </div>
                            </th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-200">
                                <div class="flex items-center justify-end gap-2">
                                    <x-heroicon-o-currency-dollar class="w-4 h-4" />
                                    Total Vendido
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse($topProducts as $index => $product)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition">
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-8 h-8 rounded-full {{ $index < 3 ? 'bg-warning-100 dark:bg-warning-500/20 text-warning-700 dark:text-warning-300' : 'bg-gray-100 dark:bg-white/10 text-gray-600 dark:text-gray-300' }} font-bold text-sm">
                                            {{ $index + 1 }}
                                        </div>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $product['name'] }}</span>
                                    </div>
                                </td>
                                <td class="text-right py-3 px-4">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300">
                                        {{ $product['total_quantity'] }}
                                    </span>
                                </td>
                                <td class="text-right py-3 px-4">
                                    <span class="font-bold text-success-600 dark:text-success-400">S/ {{ number_format($product['total_amount'], 2) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center py-12">
                                    <div class="flex flex-col items-center justify-center gap-2 text-gray-500">
                                        <x-heroicon-o-inbox style="width: 4rem; height: 4rem; margin: 0 auto;" class="text-gray-400 dark:text-gray-500" />
                                        <p class="text-base font-medium mt-2 text-gray-600 dark:text-gray-300">No hay datos disponibles</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">No se encontraron productos vendidos en este período</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Estado de Caja --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-building-storefront class="w-5 h-5 text-info-500" />
                    <span class="text-lg font-semibold">Estado de Cajas</span>
                </div>
            </x-slot>
            <x-slot name="description">
                Resumen del estado actual de las cajas registradoras
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-success-50 dark:bg-success-500/10 rounded-lg p-6 border border-success-200 dark:border-success-500/20">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-3 bg-success-200 dark:bg-success-500/20 rounded-lg">
                            <x-heroicon-o-lock-open class="w-6 h-6 text-success-700 dark:text-success-400" />
                        </div>
                        <div>
                            <div class="text-sm font-medium text-success-700 dark:text-success-400">Cajas Abiertas</div>
                            <div class="text-2xl font-bold text-success-900 dark:text-success-100">{{ $cashStatus['open_registers'] ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="pt-3 border-t border-success-200 dark:border-success-500/20">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-success-700 dark:text-success-400">Total en cajas:</span>
                            <span class="text-lg font-bold text-success-900 dark:text-success-100">S/ {{ number_format($cashStatus['total_open_amount'] ?? 0, 2) }}</span>
                        </div>
                    </div>
                </div>

                <div class="bg-danger-50 dark:bg-danger-500/10 rounded-lg p-6 border border-danger-200 dark:border-danger-500/20">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-3 bg-danger-200 dark:bg-danger-500/20 rounded-lg">
                            <x-heroicon-o-lock-closed class="w-6 h-6 text-danger-700 dark:text-danger-400" />
                        </div>
                        <div>
                            <div class="text-sm font-medium text-danger-700 dark:text-danger-400">Cerradas Hoy</div>
                            <div class="text-2xl font-bold text-danger-900 dark:text-danger-100">{{ $cashStatus['today_closed'] ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="pt-3 border-t border-danger-200 dark:border-danger-500/20">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-danger-700 dark:text-danger-400">Total cerrado:</span>
                            <span class="text-lg font-bold text-danger-900 dark:text-danger-100">S/ {{ number_format($cashStatus['today_closed_amount'] ?? 0, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
