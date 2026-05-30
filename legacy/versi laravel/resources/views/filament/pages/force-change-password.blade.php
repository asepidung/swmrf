<x-filament-panels::page.simple>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-col gap-3 mt-6">
            <x-filament::button type="submit" size="lg" class="w-full">
                Update Password & Masuk Dashboard
            </x-filament::button>

            <div class="text-center">
                {{ $this->logoutAction }}
            </div>
        </div>
    </form>
</x-filament-panels::page.simple>