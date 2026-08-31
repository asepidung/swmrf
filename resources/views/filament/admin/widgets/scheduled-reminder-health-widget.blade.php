@php
    $lastRun = $this->getLastRun();
    $healthy = $this->isHealthy();
    $summary = $this->getSummary();
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        :icon="$healthy ? 'heroicon-o-clock' : 'heroicon-o-exclamation-triangle'"
        :icon-color="$healthy ? 'success' : 'danger'"
        :heading="__('Due Date Reminder')"
    >
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-2xl font-bold text-gray-950 dark:text-white">
                    @if ($lastRun)
                        {{ $lastRun->diffForHumans() }}
                    @else
                        {{ __('Never') }}
                    @endif
                </p>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if (! $lastRun)
                        {{-- Belum pernah berjalan sama sekali: cron-nya kemungkinan
                             besar memang belum dipasang di hPanel. --}}
                        {{ __('The due date check has never run. Payable reminders are not being sent at all.') }}
                    @elseif (! $healthy)
                        {{ __('The due date check has not run recently. Reminders may have stopped without any error.') }}
                    @else
                        {{ __('Last time the system checked which payables are due.') }}
                    @endif
                </p>
            </div>

            @if ($summary['total'] > 0)
                <div class="text-right">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Needing attention') }}
                    </p>
                    <p class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ \App\Console\Commands\NotifyDuePayables::describe($summary) }}
                    </p>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
