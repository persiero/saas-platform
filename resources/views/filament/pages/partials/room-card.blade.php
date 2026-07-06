@php
    $statusClass = match ($room->status) {
        \Percy\Core\Models\Room::STATUS_AVAILABLE => 'room-available',
        \Percy\Core\Models\Room::STATUS_OCCUPIED => 'room-occupied',
        \Percy\Core\Models\Room::STATUS_DIRTY => 'room-dirty',
        \Percy\Core\Models\Room::STATUS_MAINTENANCE => 'room-maintenance',
        default => 'bg-gray-100 text-gray-800 border-gray-300',
    };

    $icon = match ($room->status) {
        \Percy\Core\Models\Room::STATUS_AVAILABLE => 'heroicon-o-check-circle',
        \Percy\Core\Models\Room::STATUS_OCCUPIED => 'heroicon-o-user-group',
        \Percy\Core\Models\Room::STATUS_DIRTY => 'heroicon-o-sparkles',
        \Percy\Core\Models\Room::STATUS_MAINTENANCE => 'heroicon-o-wrench-screwdriver',
        default => 'heroicon-o-information-circle',
    };

    $statusLabel = match ($room->status) {
        \Percy\Core\Models\Room::STATUS_AVAILABLE => 'Disponible',
        \Percy\Core\Models\Room::STATUS_OCCUPIED => 'Ocupada',
        \Percy\Core\Models\Room::STATUS_DIRTY => 'Limpieza',
        \Percy\Core\Models\Room::STATUS_MAINTENANCE => 'Mantenimiento',
        default => 'Sin estado',
    };

    $clickAction = null;
    $cursorClass = 'cursor-default';

    if ($room->status === \Percy\Core\Models\Room::STATUS_AVAILABLE) {
        $clickAction = "mountAction('checkIn', { room_id: {$room->id} })";
        $cursorClass = 'is-clickable cursor-pointer hover:shadow-xl hover:-translate-y-1 transition-all duration-200';
    } elseif ($room->status === \Percy\Core\Models\Room::STATUS_OCCUPIED) {
        $clickAction = "mountAction('manageAccount', { room_id: {$room->id} })";
        $cursorClass = 'is-clickable cursor-pointer hover:shadow-xl hover:-translate-y-1 transition-all duration-200';
    }

    $roomDisplayName = trim((string) $room->name);

    $roomNumber = preg_replace(
        '/[^0-9A-Za-z-]/',
        '',
        str_replace(['Habitación', 'Habitacion', 'Hab.', 'Hab', 'Room'], '', $roomDisplayName),
    );

    $roomNumber = filled($roomNumber) ? $roomNumber : $roomDisplayName;
@endphp

<div class="relative h-full w-full group">
    <div @if ($clickAction) wire:click="{{ $clickAction }}" @endif
        class="room-card relative overflow-hidden {{ $cursorClass }} {{ $statusClass }}">
        {{-- Franja superior decorativa --}}
        <div class="absolute inset-x-0 top-0 h-1 bg-current opacity-20"></div>

        {{-- Cabecera minimalista --}}
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="text-4xl font-black leading-none tracking-tight">
                    {{ $roomNumber }}
                </h3>

                <p class="mt-2 truncate text-xs font-bold opacity-80">
                    {{ $room->type ?: 'Habitación' }}
                </p>
            </div>

            <div class="flex shrink-0 flex-col items-end gap-2">
                <span
                    class="rounded-full bg-white/75 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide shadow-sm dark:bg-black/30">
                    {{ $statusLabel }}
                </span>

                <div class="rounded-xl bg-white/60 p-2 shadow-sm dark:bg-black/30">
                    <x-dynamic-component :component="$icon" class="h-5 w-5 opacity-80" />
                </div>
            </div>
        </div>

        {{-- Información principal --}}
        <div class="mt-4 space-y-2">
            <div
                class="flex items-center justify-between rounded-xl bg-white/65 px-3 py-2 text-xs font-bold shadow-sm dark:bg-black/30">
                <span class="opacity-75">Tarifa</span>
                <span class="text-sm font-black">S/ {{ number_format((float) $room->price_per_night, 2) }}</span>
            </div>

            @if ($room->status === \Percy\Core\Models\Room::STATUS_AVAILABLE)
                <div
                    class="rounded-xl bg-white/50 px-3 py-2 text-center text-[11px] font-black uppercase tracking-wide shadow-inner dark:bg-black/20">
                    Check-in
                </div>
            @endif

            @if ($room->status === \Percy\Core\Models\Room::STATUS_OCCUPIED)
                <div
                    class="rounded-xl bg-white/70 px-3 py-2 text-center text-[11px] font-black uppercase tracking-wide shadow-inner dark:bg-black/40">
                    <div class="flex items-center justify-center gap-1.5">
                        <x-heroicon-s-user class="h-3.5 w-3.5" />
                        <span>Ver cuenta</span>
                    </div>
                </div>
            @endif
        </div>

        {{-- Acciones para habitación ocupada --}}
        @if ($room->status === \Percy\Core\Models\Room::STATUS_OCCUPIED)
            <div class="mt-3 grid grid-cols-2 gap-2 border-t border-current/20 pt-3">
                <button type="button" wire:click.stop="mountAction('addConsumption', { room_id: {{ $room->id }} })"
                    class="btn-action btn-extras flex items-center justify-center gap-1.5 rounded-lg text-[11px] font-black transition">
                    <x-heroicon-o-shopping-bag class="h-3.5 w-3.5" />
                    Extras
                </button>

                <button type="button" wire:click.stop="mountAction('checkout', { room_id: {{ $room->id }} })"
                    class="btn-action btn-cobrar flex items-center justify-center gap-1.5 rounded-lg text-[11px] font-black transition">
                    <x-heroicon-s-credit-card class="h-3.5 w-3.5" />
                    Cobrar
                </button>
            </div>
        @endif

        {{-- Acciones para habitación sucia --}}
        @if ($room->status === \Percy\Core\Models\Room::STATUS_DIRTY)
            <div class="mt-3 grid grid-cols-2 gap-2 border-t border-current/20 pt-3">
                <button type="button"
                    wire:click.stop="mountAction('markAsAvailable', { room_id: {{ $room->id }} })"
                    class="flex items-center justify-center gap-1.5 rounded-lg border border-green-300 bg-white px-2 py-2 text-[11px] font-black text-green-700 shadow-sm transition hover:bg-green-50 dark:border-green-700 dark:bg-gray-800 dark:text-green-400 dark:hover:bg-green-900/30">
                    <x-heroicon-o-sparkles class="h-3.5 w-3.5" />
                    Limpia
                </button>

                <button type="button"
                    wire:click.stop="mountAction('markAsMaintenance', { room_id: {{ $room->id }} })"
                    class="flex items-center justify-center gap-1.5 rounded-lg border border-yellow-300 bg-white px-2 py-2 text-[11px] font-black text-yellow-700 shadow-sm transition hover:bg-yellow-50 dark:border-yellow-700 dark:bg-gray-800 dark:text-yellow-400 dark:hover:bg-yellow-900/30">
                    <x-heroicon-o-wrench-screwdriver class="h-3.5 w-3.5" />
                    Mant.
                </button>
            </div>
        @endif

        {{-- Acciones para mantenimiento --}}
        @if ($room->status === \Percy\Core\Models\Room::STATUS_MAINTENANCE)
            <div class="mt-3 border-t border-current/20 pt-3">
                <button type="button"
                    wire:click.stop="mountAction('markAsAvailable', { room_id: {{ $room->id }} })"
                    class="flex w-full items-center justify-center gap-1.5 rounded-lg border border-green-300 bg-white px-2 py-2 text-[11px] font-black text-green-700 shadow-sm transition hover:bg-green-50 dark:border-green-700 dark:bg-gray-800 dark:text-green-400 dark:hover:bg-green-900/30">
                    <x-heroicon-o-check-circle class="h-3.5 w-3.5" />
                    Marcar disponible
                </button>
            </div>
        @endif
    </div>
</div>
