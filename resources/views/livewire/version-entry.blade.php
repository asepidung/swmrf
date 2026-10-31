<div>
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-sm font-semibold text-gray-950 dark:text-white">Ver {{ $entry['version'] }}</span>
        @if ($entry['version'] === $current)
            <span class="rounded-full bg-primary-50 px-2 py-0.5 text-[10px] font-medium text-primary-600 dark:bg-primary-950/30 dark:text-primary-400">{{ __('Current') }}</span>
        @endif
        <span class="text-xs text-gray-500 dark:text-gray-400">
            {{ \Carbon\Carbon::parse($entry['date'])->translatedFormat('d M Y') }}
            &middot;
            {{ match ($entry['kind']) { 'modul' => __('New module'), 'fix' => __('Fix'), default => __('Release') } }}
        </span>
    </div>
    <p class="mt-1 text-sm font-medium text-gray-700 dark:text-gray-200">{{ $entry['title'] }}</p>
    <ul class="mt-1 list-disc space-y-0.5 pl-5 text-sm text-gray-600 dark:text-gray-300">
        @foreach ($entry['items'] as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
</div>
