{{-- Satu versi. `$terbuka`: terbuka penuh (versi terbaru) atau terlipat (versi lama, klik untuk membuka). --}}
@php $terbuka = $terbuka ?? true; @endphp
<style>
    .ver-entry { border: 1px solid rgba(128, 128, 128, .28); border-radius: .6rem; }
    .ver-entry > summary { list-style: none; cursor: pointer; display: flex; gap: .5rem; align-items: flex-start; padding: .5rem .75rem; }
    .ver-entry > summary::-webkit-details-marker { display: none; }
    .ver-entry .ver-panah { margin-top: .2rem; font-size: .75rem; color: #9ca3af; transition: transform .15s; }
    .ver-entry[open] .ver-panah { transform: rotate(90deg); }
    .ver-entry > ul { margin: 0; padding: 0 .75rem .75rem 2rem; }
    .ver-entry > ul > li + li { margin-top: .25rem; }
</style>
<details class="ver-entry" @if ($terbuka) open @endif>
    <summary>
        <span class="ver-panah" aria-hidden="true">&#9656;</span>
        <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-gray-950 dark:text-white">Ver {{ $entry['version'] }}</span>
                @if ($entry['version'] === $current)
                    <span class="rounded-full bg-primary-50 px-2 py-0.5 text-[10px] font-medium text-primary-600 dark:bg-primary-950/30 dark:text-primary-400">{{ __('Current') }}</span>
                @endif
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ \Carbon\Carbon::parse($entry['date'])->translatedFormat('d M Y') }}
                    &middot;
                    {{ match ($entry['kind']) { 'modul' => __('New module'), 'fix' => __('Fix'), default => __('Release') } }}
                </span>
            </span>
            <span class="mt-0.5 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ $entry['title'] }}</span>
        </span>
    </summary>
    <ul class="list-disc text-sm text-gray-600 dark:text-gray-300">
        @foreach ($entry['items'] as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
</details>
