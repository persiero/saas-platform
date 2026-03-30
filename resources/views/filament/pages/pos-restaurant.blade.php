<x-filament-panels::page>
    {{-- 🌟 FORZAMOS LOS COLORES CON CSS PARA EVITAR QUE TAILWIND LOS BORRE --}}
    <style>
        /* Colores de estado */
        .mesa-available { background-color: #dcfce7; color: #166534; border-color: #86efac; }
        .mesa-occupied { background-color: #ffe4e6; color: #9f1239; border-color: #fda4af; }
        .mesa-cleaning { background-color: #fef3c7; color: #92400e; border-color: #fcd34d; }

        /* Compatibilidad con Modo Oscuro de Filament */
        .dark .mesa-available { background-color: rgba(22, 101, 52, 0.4); color: #86efac; border-color: #14532d; }
        .dark .mesa-occupied { background-color: rgba(159, 18, 57, 0.4); color: #fda4af; border-color: #881337; }
        .dark .mesa-cleaning { background-color: rgba(146, 64, 14, 0.4); color: #fcd34d; border-color: #78350f; }

        /* 🌟 MAGIA CSS RESPONSIVE (AMPLIADO) 🌟 */
        .mesas-grid {
            display: grid;
            gap: 1.5rem; /* Aumentamos la separación entre mesas */
            /* 🌟 CORRECCIÓN: Subimos el mínimo a 170px para que las mesas sean más GRANDES */
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
        }

        .mesa-btn {
            aspect-ratio: 1 / 1; /* Cuadrado perfecto siempre */
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            /* 🌟 CORRECCIÓN: Aumentamos el padding interno a 1.25rem (20px) para dar AIRE */
            padding: 1.25rem;
            transition: all 0.2s ease-in-out;
        }

        .mesa-btn:hover {
            transform: scale(1.05); /* Efecto de enfoque elegante */
        }
    </style>

    <div class="space-y-8">
        @forelse($zones as $zone)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-xl font-black mb-4 text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                    {{ $zone->name }}
                </h2>

                {{-- 🌟 Usamos nuestra nueva clase mesas-grid en lugar de las de Tailwind --}}
                <div class="mesas-grid">
                    @forelse($zone->tables as $table)
                        @php
                            // 🌟 ASIGNAMOS LA CLASE CSS SEGÚN EL ESTADO
                            $statusClass = match($table->status) {
                                'available' => 'mesa-available',
                                'occupied' => 'mesa-occupied',
                                'cleaning' => 'mesa-cleaning',
                                default => 'bg-gray-100 text-gray-800 border-gray-300',
                            };

                            $icon = match($table->status) {
                                'available' => 'heroicon-o-check-circle',
                                'occupied' => 'heroicon-o-user-group',
                                'cleaning' => 'heroicon-o-sparkles',
                                default => 'heroicon-o-information-circle',
                            };
                        @endphp

                        {{-- 🌟 Usamos nuestra clase mesa-btn y quitamos las de tamaño de Tailwind --}}
                        <button
                            wire:click="openTable({{ $table->id }})"
                            class="relative rounded-2xl border-2 cursor-pointer shadow-sm group mesa-btn {{ $statusClass }}"
                        >
                            {{-- 🌟 Ícono más grande (w-8 h-8) --}}
                            <x-icon name="{{ $icon }}" class="w-8 h-8 mb-3 opacity-70 group-hover:opacity-100 transition-opacity" />

                            {{-- 🌟 Nombre de Mesa Dominante (text-2xl, 24px) --}}
                            <span class="font-bold text-2xl text-center leading-tight">{{ $table->name }}</span>

                            {{-- 🌟 Capacidad clara (text-sm, 14px) --}}
                            <span class="text-sm mt-1 opacity-70 font-medium">{{ $table->capacity }} Sillas</span>

                            {{-- 🌟 MAGIA UX: Mostrar nombre del mozo si la mesa está ocupada (Badge balanceado) --}}
                            @if($table->status === 'occupied' && $table->activeSale)
                                <div class="mt-3 text-[10px] font-bold uppercase tracking-wider bg-white/50 dark:bg-black/20 px-2.5 py-1 rounded-full flex items-center gap-1">
                                    <x-heroicon-s-user class="w-3 h-3" />
                                    {{ explode(' ', $table->activeSale->user->name)[0] ?? 'Cajero' }}
                                </div>
                            @endif

                            {{-- El puntito rojo parpadeante --}}
                            @if($table->status === 'occupied')
                                <div class="absolute top-3 right-3 flex h-3 w-3">
                                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                  <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500"></span>
                                </div>
                            @endif
                        </button>
                    @empty
                        <div class="col-span-full text-center text-gray-500 py-4 font-medium">
                            No hay mesas configuradas en esta zona.
                        </div>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-12 flex flex-col items-center justify-center text-center">

                {{-- 🌟 Contenedor circular elegante para el ícono --}}
                <div class="p-4 rounded-full bg-gray-50 dark:bg-gray-900 mb-4 inline-flex">
                    {{-- 🌟 CANDADO: width y height fijos para evitar que el SVG explote --}}
                    <x-heroicon-o-exclamation-circle class="text-gray-400" style="width: 48px; height: 48px;" />
                </div>

                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">No hay zonas configuradas</h3>
                <p class="mt-2 text-sm text-gray-500 max-w-sm mx-auto">
                    Aún no has dibujado el mapa de tu restaurante. Ve a <strong>Configuración > Zonas y Mesas</strong> para crear tu primer salón.
                </p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
