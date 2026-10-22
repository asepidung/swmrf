{{--
    Pekerjaan tertunda di Dashboard, digambar sebagai kartu: angka besar, kalimat singkat, warna menurut nada.

    Seluruh isinya berasal dari PendingTaskWidget::alerts() dan ::tasks(); berkas ini hanya menggambar.

    Tata letak dan warna ditulis dengan gaya inline dan variabel warna Filament (`--danger-600`, `--warning-600`),
    BUKAN kelas Tailwind: panel ini tidak memuat hasil build CSS aplikasi, jadi kelas seperti `grid-cols-3`,
    `bg-red-50`, atau `text-red-600` tidak ada wujudnya di sana (itu sebabnya peringatan stock opname dulu
    tampil tanpa warna). Variabel Filament sudah mengikuti mode gelap sendiri.
--}}
<x-filament-widgets::widget class="fi-wi-pending-task">
    @php
        $alerts = $this->alerts();
        $tasks = collect($this->tasks())->sortBy(fn ($task) => $task['tone'] === 'danger' ? 0 : 1)->values();
        $urgent = $tasks->where('tone', 'danger')->count();
    @endphp

    @if (filled($alerts) || $tasks->isNotEmpty())
        <div style="display: flex; flex-direction: column; gap: 16px;">

            {{-- Keadaan yang menghentikan pekerjaan, bukan tugas yang bisa dikerjakan. --}}
            @foreach ($alerts as $alert)
                <div style="display: flex; align-items: center; gap: 14px; padding: 16px 18px; border-radius: 14px; background: rgb(var(--danger-600) / 0.14); border: 1px solid rgb(var(--danger-600) / 0.45);">
                    <x-filament::icon icon="heroicon-s-exclamation-circle" style="width: 30px; height: 30px; flex-shrink: 0; color: rgb(var(--danger-500));" />
                    <div>
                        <div style="font-size: 15px; font-weight: 700; color: rgb(var(--danger-500));">{{ $alert['title'] }}</div>
                        <div style="font-size: 13px; opacity: .85;">{{ $alert['body'] }}</div>
                    </div>
                </div>
            @endforeach

            @if ($tasks->isNotEmpty())
                <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                    <div style="font-size: 16px; font-weight: 700;">{{ __('Needs your attention') }}</div>
                    <div style="font-size: 13px; opacity: .7;">
                        {{ __(':total items', ['total' => $tasks->count()]) }}@if ($urgent > 0) · <span style="color: rgb(var(--danger-500)); font-weight: 600;">{{ __(':urgent urgent', ['urgent' => $urgent]) }}</span>@endif
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px;">
                    @foreach ($tasks as $task)
                        @php $danger = $task['tone'] === 'danger'; $color = $danger ? 'var(--danger-500)' : 'var(--warning-500)'; @endphp
                        <a href="{{ $task['url'] }}"
                           style="display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-radius: 14px; text-decoration: none; color: inherit; background: rgb({{ $color }} / 0.10); border: 1px solid rgb({{ $color }} / 0.30); border-left: 5px solid rgb({{ $color }}); transition: transform .12s ease, box-shadow .12s ease;"
                           onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgb({{ $color }} / 0.25)'"
                           onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                            <div style="min-width: 54px; text-align: center; font-size: 30px; line-height: 1; font-weight: 800; color: rgb({{ $color }});">{{ $task['count'] }}</div>
                            <div style="flex: 1; font-size: 13.5px; line-height: 1.35; font-weight: 500;">{{ $task['text'] }}</div>
                            <x-filament::icon icon="heroicon-m-chevron-right" style="width: 18px; height: 18px; flex-shrink: 0; opacity: .5;" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</x-filament-widgets::widget>
