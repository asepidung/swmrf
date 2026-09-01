@php
    $lastRun = $this->getLastRun();
    $healthy = $this->isHealthy();
    $summary = $this->getSummary();
    $unlocked = $this->getUnlockedSummary();
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        :icon="$healthy ? 'heroicon-o-clock' : 'heroicon-o-exclamation-triangle'"
        :icon-color="$healthy ? 'success' : 'danger'"
        :heading="__('Scheduled Reminders')"
    >
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-lg font-bold dark:text-white">
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
                        {{ __('No reminder has been sent yet.') }}
                    @elseif (! $healthy)
                        {{ __('A check has not run recently.') }}
                    @else
                        {{ __('Last scheduled check.') }}
                    @endif
                </p>
            </div>

            @if ($summary['total'] > 0 || $unlocked['total'] > 0)
                <div class="text-right">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Needing attention') }}
                    </p>

                    @if ($summary['total'] > 0)
                        <p class="text-lg font-semibold text-gray-950 dark:text-white">
                            {{ \App\Console\Commands\NotifyDuePayables::describe($summary) }}
                        </p>
                    @endif

                    {{-- Goods Receipt yang menggantung: selama belum dikunci,
                         hutangnya belum terbentuk sama sekali. --}}
                    @if ($unlocked['total'] > 0)
                        <p class="text-lg font-semibold text-danger-600">
                            {{ \App\Console\Commands\NotifyUnlockedGoodsReceipts::describe($unlocked) }}
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
