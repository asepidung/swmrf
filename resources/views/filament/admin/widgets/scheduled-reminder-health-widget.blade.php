@php
    // Widget ini hanya tampil saat ada yang salah, jadi tidak ada lagi cabang
    // "sehat" di sini. Yang tersisa cuma dua keadaan: belum pernah berjalan,
    // atau sudah lama tidak berjalan.
    $lastRun = \App\Filament\Admin\Widgets\ScheduledReminderHealthWidget::getLastRun();
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-exclamation-triangle"
        icon-color="danger"
        :heading="__('Scheduled Reminders')"
    >
        <p class="text-sm text-gray-600 dark:text-gray-300">
            @if (! $lastRun)
                {{-- Belum pernah berjalan sama sekali: cron-nya kemungkinan
                     besar memang belum dipasang di hPanel. --}}
                {{ __('The scheduled check has never run, so no reminder is being sent.') }}
            @else
                {{ __('The scheduled check last ran :time.', ['time' => $lastRun->diffForHumans()]) }}
            @endif
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
