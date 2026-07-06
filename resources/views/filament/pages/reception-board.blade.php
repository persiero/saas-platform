<x-filament-panels::page>
    <style>
        /* 🌟 COLORES DE ESTADO (Mantenidos) */
        .room-available {
            background-color: #dcfce7;
            color: #166534;
            border-color: #86efac;
        }

        .room-occupied {
            background-color: #ffe4e6;
            color: #9f1239;
            border-color: #fda4af;
        }

        .room-dirty {
            background-color: #fef3c7;
            color: #92400e;
            border-color: #fcd34d;
        }

        .room-maintenance {
            background-color: #f3f4f6;
            color: #374151;
            border-color: #d1d5db;
        }

        .dark .room-available {
            background-color: rgba(22, 101, 52, 0.4);
            color: #86efac;
            border-color: #14532d;
        }

        .dark .room-occupied {
            background-color: rgba(159, 18, 57, 0.4);
            color: #fda4af;
            border-color: #881337;
        }

        .dark .room-dirty {
            background-color: rgba(146, 64, 14, 0.4);
            color: #fcd34d;
            border-color: #78350f;
        }

        .dark .room-maintenance {
            background-color: rgba(55, 65, 81, 0.4);
            color: #d1d5db;
            border-color: #374151;
        }

        /* 🌟 DISEÑO COMPACTO DE LA GRILLA Y TARJETAS */
        .hotel-summary-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }

        @media (min-width: 640px) {
            .hotel-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .hotel-summary-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }

        .hotel-summary-card {
            border-radius: 1rem;
            border: 1px solid rgb(229 231 235);
            background: white;
            padding: 1rem;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
        }

        .dark .hotel-summary-card {
            border-color: rgb(55 65 81);
            background: rgb(17 24 39);
        }

        .rooms-grid {
            display: grid;
            gap: 0.85rem;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            align-items: stretch;
        }

        @media (min-width: 768px) {
            .rooms-grid {
                gap: 1rem;
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            }
        }

        @media (min-width: 1280px) {
            .rooms-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }

        .room-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            min-height: 170px;
            padding: 1rem;
            transition: all 0.2s ease-in-out;
            border-width: 2px;
            border-radius: 1rem;
        }

        @media (min-width: 768px) {
            .room-card {
                min-height: 180px;
            }
        }

        .room-card.is-clickable:hover {
            transform: scale(1.03);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        /* 🌟 BOTONES BLINDADOS (Alto Contraste y más delgados) */
        .btn-action {
            padding: 0.5rem 0.625rem;
            min-height: 2.1rem;
        }

        /* py-1.5 */

        .btn-extras {
            background-color: #ffffff;
            color: #9f1239;
            border: 1px solid #fda4af;
        }

        .btn-extras:hover {
            background-color: #fff1f2;
        }

        .btn-cobrar {
            background-color: #e11d48;
            color: #ffffff;
            border: none;
            box-shadow: 0 4px 6px -1px rgba(225, 29, 72, 0.4);
        }

        .btn-cobrar:hover {
            background-color: #be123c;
        }

        .dark .btn-extras {
            background-color: rgba(0, 0, 0, 0.3);
            color: #fecdd3;
            border: 1px solid rgba(253, 164, 175, 0.3);
        }

        .dark .btn-extras:hover {
            background-color: rgba(0, 0, 0, 0.5);
        }

        .dark .btn-cobrar {
            background-color: #f43f5e;
            color: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5);
        }

        .dark .btn-cobrar:hover {
            background-color: #e11d48;
        }
    </style>

    {{-- Contenedor principal con separación entre Pisos/Zonas --}}
    <div class="space-y-10">

        <div class="hotel-summary-grid">
            <div class="hotel-summary-card">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-primary-100 p-2 text-primary-600 dark:bg-primary-900/30">
                        <x-heroicon-o-key class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total</p>
                        <p class="text-xl font-black text-gray-900 dark:text-white">{{ $summary['total'] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="hotel-summary-card">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-green-100 p-2 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                        <x-heroicon-o-check-circle class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-green-700 dark:text-green-300">
                            Disponibles</p>
                        <p class="text-xl font-black text-green-900 dark:text-green-100">
                            {{ $summary['available'] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="hotel-summary-card">
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

            <div class="hotel-summary-card">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-amber-100 p-2 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                        <x-heroicon-o-sparkles class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">
                            Limpieza</p>
                        <p class="text-xl font-black text-amber-900 dark:text-amber-100">{{ $summary['dirty'] ?? 0 }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="hotel-summary-card">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-gray-100 p-2 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        <x-heroicon-o-wrench-screwdriver class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                            Mantenimiento</p>
                        <p class="text-xl font-black text-gray-900 dark:text-gray-100">
                            {{ $summary['maintenance'] ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- 🌟 ITERAMOS SOBRE CADA PISO/ZONA --}}
        @foreach ($zones as $zone)
            {{-- Solo dibujamos la zona si tiene habitaciones asignadas --}}
            @if ($zone->rooms->count() > 0)
                <div
                    class="rounded-2xl border border-gray-200 bg-white/70 p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/50 sm:p-6">

                    {{-- Título de la Zona (Ej: "PISO 1", "ZONA VIP") --}}
                    <div
                        class="mb-5 flex flex-col gap-3 border-b border-gray-200 pb-4 dark:border-gray-700 sm:flex-row sm:items-center">
                        <x-heroicon-o-building-office-2 class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                        <h2
                            class="text-xl font-black uppercase tracking-wider text-gray-800 dark:text-gray-100 sm:text-2xl">
                            {{ $zone->name }}
                        </h2>
                        <span <span
                            class="w-fit rounded-full bg-gray-100 px-3 py-1 text-xs font-bold text-gray-500 dark:bg-gray-700 sm:ml-auto sm:text-sm">
                            {{ $zone->rooms->count() }} Hab.
                        </span>
                    </div>

                    {{-- Grilla de Habitaciones de ESTA Zona --}}
                    <div class="rooms-grid">
                        @foreach ($zone->rooms as $room)
                            {{-- INCLUIMOS EL CÓDIGO DE LA TARJETA AQUÍ (Ver Nota Abajo) --}}
                            @include('filament.pages.partials.room-card', ['room' => $room])
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        {{-- 🌟 CASO ESPECIAL: Habitaciones "Huérfanas" (Sin zona asignada) --}}
        @if ($unassignedRooms->count() > 0)
            <div
                class="bg-gray-100 dark:bg-gray-900 p-6 rounded-2xl border-2 border-dashed border-gray-300 dark:border-gray-700">
                <div class="flex items-center gap-3 mb-6 pb-3 border-b border-gray-300 dark:border-gray-700">
                    <x-heroicon-o-question-mark-circle class="w-7 h-7 text-gray-500" />
                    <h2 class="text-xl font-black text-gray-600 dark:text-gray-400">Sin Asignar</h2>
                    <span class="text-xs text-gray-500 font-medium ml-2">(Falta asignarles un Piso en
                        Configuración)</span>
                </div>
                <div class="rooms-grid">
                    @foreach ($unassignedRooms as $room)
                        @include('filament.pages.partials.room-card', ['room' => $room])
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Mensaje de Vacío si el hotel está totalmente en cero --}}
        @if ($zones->isEmpty() && $unassignedRooms->isEmpty())
            <div
                class="flex flex-col items-center justify-center p-16 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm">
                <div class="p-4 rounded-full bg-gray-50 dark:bg-gray-800 mb-4 inline-flex">
                    <x-heroicon-o-key class="w-12 h-12 text-gray-400" />
                </div>
                <h3 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Sin habitaciones</h3>
                <p class="text-gray-500 dark:text-gray-400 mt-2 max-w-sm text-center">Registra tu primera habitación
                    para empezar a operar el hotel.</p>
            </div>
        @endif

    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
