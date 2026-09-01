{{--
    Daftar pekerjaan tertunda di Dashboard.

    Seluruh isinya berasal dari PendingTaskWidget::alerts() dan ::tasks();
    berkas ini hanya menggambar. Sebelumnya tiap baris ditulis tangan --
    dua puluh blok HTML yang hampir sama, masing-masing dengan kelasnya
    sendiri, warnanya sendiri, dan kalimatnya sendiri.

    Warnanya memakai nama SEMANTIK Filament (danger, warning), bukan nama
    warna bawaan Tailwind. Panel ini tidak memuat hasil build CSS aplikasi,
    dan kelas seperti `bg-red-50` atau `text-red-600` tidak ada wujudnya di
    sana. Peringatan stock opname -- notifikasi paling keras di halaman ini --
    selama ini memakai kelas-kelas itu, sehingga ia berkedip tanpa satu warna
    pun, tanpa ada error yang memberitahu.
--}}
<x-filament-widgets::widget class="fi-wi-pending-task">
    @php
        $alerts = $this->alerts();
        $tasks = $this->tasks();
    @endphp

    @if (filled($alerts) || filled($tasks))
        <div class="space-y-2">

            {{-- Keadaan yang menghentikan pekerjaan, bukan tugas yang bisa dikerjakan. --}}
            @foreach ($alerts as $alert)
                <div class="flex items-center gap-3 rounded-lg border border-danger-200 bg-danger-50 p-4 shadow-sm dark:bg-danger-900">
                    <x-filament::icon
                        icon="heroicon-s-exclamation-circle"
                        class="h-7 w-7 shrink-0 text-danger-600 dark:text-danger-400"
                    />
                    <div>
                        <p class="text-sm font-bold text-danger-700 dark:text-danger-400">
                            {{ $alert['title'] }}
                        </p>
                        <p class="text-sm text-danger-600 dark:text-danger-400">
                            {{ $alert['body'] }}
                        </p>
                    </div>
                </div>
            @endforeach

            {{-- Pekerjaan tertunda. Satu bentuk untuk semuanya. --}}
            @foreach ($tasks as $task)
                <a
                    href="{{ $task['url'] }}"
                    class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 shadow-sm transition dark:border-gray-800 dark:bg-gray-900"
                >
                    <x-filament::icon
                        icon="heroicon-o-exclamation-triangle"
                        @class([
                            'h-5 w-5 shrink-0',
                            'text-danger-600 dark:text-danger-400' => $task['tone'] === 'danger',
                            'text-warning-600 dark:text-warning-400' => $task['tone'] !== 'danger',
                        ])
                    />
                    <p @class([
                        'text-sm',
                        'font-semibold text-danger-700 dark:text-danger-400' => $task['tone'] === 'danger',
                        'font-medium text-gray-700 dark:text-gray-200' => $task['tone'] !== 'danger',
                    ])>
                        {{ $task['label'] }}
                    </p>
                </a>
            @endforeach
        </div>
    @endif
</x-filament-widgets::widget>
