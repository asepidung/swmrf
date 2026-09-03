@php
    $coverage = $this->getCoverage();
    $percentage = $this->getPercentage();
    $subscribed = $coverage['subscribed'];
    $total = $coverage['total'];

    // Widget ini hanya tampil saat masih ada yang tertinggal, jadi tidak ada
    // nada "success" di sini: selalu ada sisa yang perlu ditagih. Di bawah
    // separuh, notifikasi praktis tidak sampai ke sebagian besar orang.
    $tone = $percentage >= 50 ? 'warning' : 'danger';
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-bell-slash"
        :icon-color="$tone"
        :heading="__('Device Notifications')"
    >
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-2xl font-bold text-gray-950 dark:text-white">
                    {{ $subscribed }} / {{ $total }}
                    <span class="text-base font-normal text-gray-500 dark:text-gray-400">
                        ({{ $percentage }}%)
                    </span>
                </p>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if ($subscribed === 0)
                        {{ __('Nobody has turned notifications on yet, so no alert is reaching anyone.') }}
                    @elseif ($percentage < 50)
                        {{ __('Most users still miss every alert. Ask them to turn notifications on from the bell icon.') }}
                    @else
                        {{ __(':count of :total active users still miss every alert. Ask them to turn notifications on from the bell icon.', ['count' => $total - $subscribed, 'total' => $total]) }}
                    @endif
                </p>
            </div>

            @unless ($this->isCurrentUserSubscribed())
                <p class="text-sm font-medium text-{{ $tone }}-600 dark:text-{{ $tone }}-400">
                    {{ __('You are not subscribed on this device yet.') }}
                </p>
            @endunless
        </div>

        <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
            <div
                @class([
                    'h-2 rounded-full',
                    'bg-warning-500' => $tone === 'warning',
                    'bg-danger-500' => $tone === 'danger',
                ])
                style="width: {{ max($percentage, 2) }}%"
            ></div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
