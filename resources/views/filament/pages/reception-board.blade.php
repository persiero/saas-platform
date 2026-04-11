<x-filament-panels::page>
    <style>
        /* 🌟 COLORES DE ESTADO (Mantenidos) */
        .room-available { background-color: #dcfce7; color: #166534; border-color: #86efac; }
        .room-occupied { background-color: #ffe4e6; color: #9f1239; border-color: #fda4af; }
        .room-dirty { background-color: #fef3c7; color: #92400e; border-color: #fcd34d; }
        .room-maintenance { background-color: #f3f4f6; color: #374151; border-color: #d1d5db; }

        .dark .room-available { background-color: rgba(22, 101, 52, 0.4); color: #86efac; border-color: #14532d; }
        .dark .room-occupied { background-color: rgba(159, 18, 57, 0.4); color: #fda4af; border-color: #881337; }
        .dark .room-dirty { background-color: rgba(146, 64, 14, 0.4); color: #fcd34d; border-color: #78350f; }
        .dark .room-maintenance { background-color: rgba(55, 65, 81, 0.4); color: #d1d5db; border-color: #374151; }

        /* 🌟 DISEÑO COMPACTO DE LA GRILLA Y TARJETAS */
        .rooms-grid {
            display: grid;
            gap: 1rem; /* Margen reducido */
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); /* Tarjetas más angostas */
            align-items: stretch; /* Iguala la altura de toda la fila */
        }

        .room-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            min-height: 160px; /* Altura más baja */
            padding: 1rem; /* Padding más ajustado */
            transition: all 0.2s ease-in-out;
            border-width: 2px;
            border-radius: 0.75rem; /* Bordes un poco menos pronunciados */
        }

        .room-card.is-clickable:hover {
            transform: scale(1.03);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        /* 🌟 BOTONES BLINDADOS (Alto Contraste y más delgados) */
        .btn-action { padding-top: 0.375rem; padding-bottom: 0.375rem; } /* py-1.5 */

        .btn-extras { background-color: #ffffff; color: #9f1239; border: 1px solid #fda4af; }
        .btn-extras:hover { background-color: #fff1f2; }
        .btn-cobrar { background-color: #e11d48; color: #ffffff; border: none; box-shadow: 0 4px 6px -1px rgba(225, 29, 72, 0.4); }
        .btn-cobrar:hover { background-color: #be123c; }

        .dark .btn-extras { background-color: rgba(0, 0, 0, 0.3); color: #fecdd3; border: 1px solid rgba(253, 164, 175, 0.3); }
        .dark .btn-extras:hover { background-color: rgba(0, 0, 0, 0.5); }
        .dark .btn-cobrar { background-color: #f43f5e; color: #ffffff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .dark .btn-cobrar:hover { background-color: #e11d48; }
    </style>

    {{-- Contenedor principal con separación entre Pisos/Zonas --}}
    <div class="space-y-10">

        {{-- 🌟 ITERAMOS SOBRE CADA PISO/ZONA --}}
        @foreach($zones as $zone)
            {{-- Solo dibujamos la zona si tiene habitaciones asignadas --}}
            @if($zone->rooms->count() > 0)
                <div class="bg-white/50 dark:bg-gray-800/50 p-6 rounded-2xl border border-gray-200 dark:border-gray-700">

                    {{-- Título de la Zona (Ej: "PISO 1", "ZONA VIP") --}}
                    <div class="flex items-center gap-3 mb-6 border-b border-gray-200 dark:border-gray-700 pb-3">
                        <x-heroicon-o-building-office-2 class="w-7 h-7 text-primary-600 dark:text-primary-400" />
                        <h2 class="text-2xl font-black text-gray-800 dark:text-gray-100 uppercase tracking-wider">
                            {{ $zone->name }}
                        </h2>
                        <span class="ml-auto text-sm font-bold text-gray-500 bg-gray-100 dark:bg-gray-700 px-3 py-1 rounded-full">
                            {{ $zone->rooms->count() }} Hab.
                        </span>
                    </div>

                    {{-- Grilla de Habitaciones de ESTA Zona --}}
                    <div class="rooms-grid">
                        @foreach($zone->rooms as $room)
                            {{-- INCLUIMOS EL CÓDIGO DE LA TARJETA AQUÍ (Ver Nota Abajo) --}}
                            @include('filament.pages.partials.room-card', ['room' => $room])
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        {{-- 🌟 CASO ESPECIAL: Habitaciones "Huérfanas" (Sin zona asignada) --}}
        @if($unassignedRooms->count() > 0)
            <div class="bg-gray-100 dark:bg-gray-900 p-6 rounded-2xl border-2 border-dashed border-gray-300 dark:border-gray-700">
                <div class="flex items-center gap-3 mb-6 pb-3 border-b border-gray-300 dark:border-gray-700">
                    <x-heroicon-o-question-mark-circle class="w-7 h-7 text-gray-500" />
                    <h2 class="text-xl font-black text-gray-600 dark:text-gray-400">Sin Asignar</h2>
                    <span class="text-xs text-gray-500 font-medium ml-2">(Falta asignarles un Piso en Configuración)</span>
                </div>
                <div class="rooms-grid">
                    @foreach($unassignedRooms as $room)
                        @include('filament.pages.partials.room-card', ['room' => $room])
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Mensaje de Vacío si el hotel está totalmente en cero --}}
        @if($zones->isEmpty() && $unassignedRooms->isEmpty())
            <div class="flex flex-col items-center justify-center p-16 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm">
                <div class="p-4 rounded-full bg-gray-50 dark:bg-gray-800 mb-4 inline-flex">
                    <x-heroicon-o-key class="w-12 h-12 text-gray-400" />
                </div>
                <h3 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Sin habitaciones</h3>
                <p class="text-gray-500 dark:text-gray-400 mt-2 max-w-sm text-center">Registra tu primera habitación para empezar a operar el hotel.</p>
            </div>
        @endif

    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
