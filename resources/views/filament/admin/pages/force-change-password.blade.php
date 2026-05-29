<x-filament-panels::page>
    <div class="max-w-xl">
        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('You are using a default or weak password. Please set a new password of at least 8 characters to secure your account and continue.') }}
        </div>

        <form wire:submit="changePassword" class="space-y-6">
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getFormActions()"
            />
        </form>
    </div>
</x-filament-panels::page>
