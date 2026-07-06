<x-filament-panels::page>
    <style>
        /* 🌟 COLORES DE ESTADO (Mantenemos los que ya funcionan perfecto) */
        .mesa-available {
            background-color: #dcfce7;
            color: #166534;
            border-color: #86efac;
        }

        .mesa-occupied {
            background-color: #ffe4e6;
            color: #9f1239;
            border-color: #fda4af;
        }

        .mesa-cleaning {
            background-color: #fef3c7;
            color: #92400e;
            border-color: #fcd34d;
        }

        .dark .mesa-available {
            background-color: rgba(22, 101, 52, 0.4);
            color: #86efac;
            border-color: #14532d;
        }

        .dark .mesa-occupied {
            background-color: rgba(159, 18, 57, 0.4);
            color: #fda4af;
            border-color: #881337;
        }

        .dark .mesa-cleaning {
            background-color: rgba(146, 64, 14, 0.4);
            color: #fcd34d;
            border-color: #78350f;
        }

        /* 🌟 GRILLA Y TARJETAS SIMÉTRICAS */
        .mesas-grid {
            display: grid;
            gap: 0.9rem;
            grid-template-columns: repeat(auto-fill, minmax(128px, 1fr));
        }

        @media (min-width: 640px) {
            .mesas-grid {
                gap: 1rem;
                grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));
            }
        }

        @media (min-width: 1280px) {
            .mesas-grid {
                gap: 1.25rem;
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            }
        }

        .mesa-btn {
            /* 🌟 CLAVE: Forzamos el cuadrado perfecto independientemente del contenido */
            aspect-ratio: 1 / 1;
            width: 100%;
            /* En lugar de alinear todo al centro, usamos flex y distribuimos el espacio */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            padding: 0.85rem;
            transition: all 0.2s ease-in-out;
            border-width: 2px;
            border-radius: 1rem;
        }

        @media (min-width: 768px) {
            .mesa-btn {
                padding: 1rem;
                border-radius: 1.25rem;
            }
        }

        .mesa-btn:hover {
            transform: scale(1.03);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .restaurant-summary-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }

        @media (min-width: 640px) {
            .restaurant-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .restaurant-summary-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        .restaurant-summary-card {
            border-radius: 1rem;
            border: 1px solid rgb(229 231 235);
            background: white;
            padding: 1rem;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
        }

        .dark .restaurant-summary-card {
            border-color: rgb(55 65 81);
            background: rgb(17 24 39);
        }
    </style>

    <div class="space-y-8">
        <div class="restaurant-summary-grid">
            <div class="restaurant-summary-card">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-primary-100 p-2 text-primary-600 dark:bg-primary-900/30">
                        <x-heroicon-o-squares-2x2 class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total</p>
                        <p class="text-xl font-black text-gray-900 dark:text-white">{{ $summary['total'] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="restaurant-summary-card">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-green-100 p-2 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                        <x-heroicon-o-check-circle class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-green-700 dark:text-green-300">
                            Libres</p>
                        <p class="text-xl font-black text-green-900 dark:text-green-100">
                            {{ $summary['available'] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="restaurant-summary-card">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-rose-100 p-2 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">
                        <x-heroicon-o-user-group class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300">
                            Ocupadas</p>
                        <p class="text-xl font-black text-rose-900 dark:text-rose-100">{{ $summary['occupied'] ?? 0 }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="restaurant-summary-card">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-amber-100 p-2 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                        <x-heroicon-o-sparkles class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">
                            Limpieza</p>
                        <p class="text-xl font-black text-amber-900 dark:text-amber-100">{{ $summary['cleaning'] ?? 0 }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
        @forelse($zones as $zone)
            {{-- 🌟 Contenedor del Salón (Estilo igual al del Hotel) --}}
            <div
                class="bg-white/50 dark:bg-gray-800/50 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">

                {{-- Cabecera del Salón --}}
                <div
                    class="mb-5 flex flex-col gap-3 border-b border-gray-200 pb-4 dark:border-gray-700 sm:flex-row sm:items-center">
                    <x-heroicon-o-squares-2x2 class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                    <h2 class="text-xl font-black text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                        {{ $zone->name }}
                    </h2>
                    <span
                        class="w-fit rounded-full bg-gray-100 px-3 py-1 text-xs font-bold text-gray-500 dark:bg-gray-700 sm:ml-auto">
                        {{ $zone->tables->count() }} Mesas
                    </span>
                </div>

                <div class="mesas-grid">
                    @forelse($zone->tables as $table)
                        @php
                            $statusClass = match ($table->status) {
                                'available' => 'mesa-available',
                                'occupied' => 'mesa-occupied',
                                'cleaning' => 'mesa-cleaning',
                                default => 'bg-gray-100 text-gray-800 border-gray-300',
                            };

                            $icon = match ($table->status) {
                                'available' => 'heroicon-o-check-circle',
                                'occupied' => 'heroicon-o-user-group',
                                'cleaning' => 'heroicon-o-sparkles',
                                default => 'heroicon-o-information-circle',
                            };
                        @endphp

                        <button wire:click="openTable({{ $table->id }})"
                            class="cursor-pointer group mesa-btn {{ $statusClass }}">
                            {{-- 🌟 PARTE SUPERIOR (Siempre fija arriba) --}}
                            <div class="flex flex-col items-center w-full">
                                <x-dynamic-component :component="$icon"
                                    class="w-8 h-8 mb-2 opacity-70 group-hover:opacity-100 transition-opacity" />
                                <span
                                    class="text-center text-base font-black leading-tight sm:text-xl">{{ $table->name }}</span>
                                <span class="mt-1 text-[10px] font-bold tracking-wide opacity-75 sm:text-[11px]">
                                    {{ $table->capacity }} Sillas
                                </span>
                            </div>

                            {{-- 🌟 PARTE INFERIOR (Ocupa el espacio vacío, mantiene la simetría) --}}
                            <div class="mt-auto w-full flex justify-center h-6">
                                @php
                                    // 🌟 Capturamos la venta pendiente que mandamos desde el controlador
                                    $ventaPendiente = $table->sales->first();
                                @endphp

                                @if ($table->status === 'occupied' && $ventaPendiente)
                                    <div
                                        class="text-[10px] font-bold uppercase tracking-wider bg-white/50 dark:bg-black/20 px-3 py-1 rounded-lg flex items-center justify-center gap-1.5 shadow-inner">
                                        <x-heroicon-s-user class="w-3 h-3" />
                                        {{ explode(' ', $ventaPendiente->user->name)[0] ?? 'Mozo' }}
                                    </div>
                                @endif
                            </div>
                        </button>
                    @empty
                        <div
                            class="col-span-full text-center text-gray-500 py-8 font-medium bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
                            No hay mesas configuradas en este salón.
                        </div>
                    @endforelse
                </div>
            </div>
        @empty
            <div
                class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-12 flex flex-col items-center justify-center text-center">
                <div class="p-4 rounded-full bg-gray-50 dark:bg-gray-900 mb-4 inline-flex">
                    <x-heroicon-o-exclamation-circle class="text-gray-400" style="width: 48px; height: 48px;" />
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">No hay salones configurados</h3>
                <p class="mt-2 text-sm text-gray-500 max-w-sm mx-auto">
                    Aún no has dibujado el mapa de tu restaurante. Ve a <strong>Configuración > Zonas y Pisos</strong>
                    para crear tu primer salón.
                </p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
