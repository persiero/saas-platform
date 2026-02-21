<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        
        {{-- Aquí se pintan tus pestañas --}}
        {{ $this->form }}

        {{-- Botón limpio sin el wrapper estricto de Filament --}}
        <div class="mt-6 flex justify-start">
            <x-filament::button type="submit" size="lg" color="primary">
                Guardar Configuración
            </x-filament::button>
        </div>

    </x-filament-panels::form>
</x-filament-panels::page>