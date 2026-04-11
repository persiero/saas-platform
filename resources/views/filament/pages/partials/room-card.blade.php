@php
    $statusClass = match($room->status) {
        'available' => 'room-available',
        'occupied' => 'room-occupied',
        'dirty' => 'room-dirty',
        'maintenance' => 'room-maintenance',
        default => 'bg-gray-100 text-gray-800 border-gray-300',
    };

    $icon = match($room->status) {
        'available' => 'heroicon-o-check-circle',
        'occupied' => 'heroicon-o-user',
        'dirty' => 'heroicon-o-sparkles',
        'maintenance' => 'heroicon-o-wrench',
        default => 'heroicon-o-information-circle',
    };

    // 🌟 MAGIA UX: Asignamos la acción del Modal según el estado de la habitación
    $clickAction = '';
    $cursorClass = 'cursor-default';

    if ($room->status === 'available') {
        $clickAction = "mountAction('checkInAction', { room_id: {$room->id} })";
        $cursorClass = 'is-clickable cursor-pointer hover:shadow-xl hover:-translate-y-1 transition-all duration-200';
    } elseif ($room->status === 'occupied') {
        // Llama a la acción manageAccount que creamos en ReceptionBoard.php
        $clickAction = "mountAction('manageAccount', { room_id: {$room->id} })";
        $cursorClass = 'is-clickable cursor-pointer hover:shadow-xl hover:-translate-y-1 transition-all duration-200';
    }
@endphp

<div class="relative w-full h-full group">
    <div
        @if($clickAction) wire:click="{{ $clickAction }}" @endif
        class="room-card relative {{ $cursorClass }} {{ $statusClass }}">

        {{-- Argollitas Decorativas --}}
        <div class="absolute top-2 left-2 flex space-x-0.5 opacity-60">
            <span class="w-1.5 h-1.5 rounded-full border-2 border-current"></span>
            <span class="w-1.5 h-1.5 rounded-full border-2 border-current"></span>
        </div>

        {{-- Cabecera Compacta --}}
        <div class="flex justify-between items-start mb-3 mt-2">
            <div>
                <h3 class="text-lg font-black leading-none">{{ $room->name }}</h3>
                <p class="text-xs font-bold opacity-80 mt-1">{{ $room->type }}</p>
            </div>
            <x-dynamic-component :component="$icon" class="w-7 h-7 opacity-60" />
        </div>

        {{-- Cuerpo y Botones Compactos --}}
        <div class="space-y-3 mt-auto">
            <div class="flex justify-between items-center text-xs font-bold bg-white/60 dark:bg-black/30 px-2 py-1.5 rounded-md shadow-sm">
                <span>Tarifa:</span>
                <span>S/ {{ number_format($room->price_per_night, 2) }}</span>
            </div>

            @if($room->status === 'occupied')
                <div class="mt-2 flex flex-col gap-1.5">

                    {{-- 🌟 Pista visual para el usuario: Añadimos un pequeño texto que invita a hacer clic --}}
                    <div class="text-[9px] font-black uppercase tracking-widest bg-white/80 dark:bg-black/50 px-2 py-1 rounded-md flex items-center justify-center gap-1.5 text-danger-900 dark:text-danger-200 shadow-inner">
                        <x-heroicon-s-user class="w-3 h-3" />
                        <span>Ocupada <span class="opacity-60 ml-1 font-normal capitalize">(Detalle)</span></span>
                    </div>

                    <div class="flex gap-1.5 w-full mt-1">
                        {{-- 🌟 BLINDAJE: El .stop impide que al hacer clic en 'Extras' se abra también la 'Cuenta' --}}
                        <button wire:click.stop="mountAction('addConsumptionAction', { room_id: {{ $room->id }} })"
                                class="btn-action btn-extras flex-1 flex items-center justify-center gap-1 text-[10px] font-bold rounded-md transition-colors">
                            <x-heroicon-o-shopping-bag class="w-3 h-3" /> Extras
                        </button>

                        <button wire:click.stop="mountAction('checkoutAction', { room_id: {{ $room->id }} })"
                                class="btn-action btn-cobrar flex-1 flex items-center justify-center gap-1 text-[10px] font-bold rounded-md transition-colors">
                            <x-heroicon-s-credit-card class="w-3 h-3" /> Cobrar
                        </button>
                    </div>
                </div>
            @endif
        </div>

        {{-- 🌟 BOTONES DE ACCIÓN RÁPIDA (También protegidos con .stop) --}}
        @if($room->status === \Percy\Core\Models\Room::STATUS_DIRTY)
            <div class="mt-3 flex gap-2 w-full pt-3 border-t border-yellow-300 dark:border-yellow-700">
                <button wire:click.stop="mountAction('markAsAvailable', { room_id: {{ $room->id }} })" class="flex-1 flex items-center justify-center gap-1 py-1.5 px-2 bg-white dark:bg-gray-800 text-green-700 dark:text-green-400 border border-green-300 dark:border-green-700 rounded-lg shadow-sm hover:bg-green-50 dark:hover:bg-green-900/30 transition text-xs font-bold">
                    <x-heroicon-o-sparkles class="w-4 h-4" /> Limpia
                </button>
                <button wire:click.stop="mountAction('markAsMaintenance', { room_id: {{ $room->id }} })" class="flex-1 flex items-center justify-center gap-1 py-1.5 px-2 bg-white dark:bg-gray-800 text-yellow-700 dark:text-yellow-400 border border-yellow-300 dark:border-yellow-700 rounded-lg shadow-sm hover:bg-yellow-50 dark:hover:bg-yellow-900/30 transition text-xs font-bold">
                    <x-heroicon-o-wrench class="w-4 h-4" /> Manten.
                </button>
            </div>
        @elseif($room->status === \Percy\Core\Models\Room::STATUS_MAINTENANCE)
            <div class="mt-3 flex gap-2 w-full pt-3 border-t border-gray-300 dark:border-gray-600">
                <button wire:click.stop="mountAction('markAsAvailable', { room_id: {{ $room->id }} })" class="w-full flex items-center justify-center gap-1 py-1.5 px-2 bg-white dark:bg-gray-800 text-green-700 dark:text-green-400 border border-green-300 dark:border-green-700 rounded-lg shadow-sm hover:bg-green-50 dark:hover:bg-green-900/30 transition text-xs font-bold">
                    <x-heroicon-o-sparkles class="w-4 h-4" /> Reparada / Disponible
                </button>
            </div>
        @endif
    </div>
</div>
