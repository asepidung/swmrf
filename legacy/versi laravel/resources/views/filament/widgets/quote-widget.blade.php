<x-filament-widgets::widget>
    <x-filament::section class="h-full flex flex-col justify-between">
        {{-- Slot Heading --}}
        <x-slot name="heading">
            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                <x-heroicon-m-chat-bubble-left-right class="w-5 h-5" />
                <span>Quotes Of The Day</span>
            </div>
        </x-slot>

        @if($quote)
            <div class="py-2 flex-1">
                {{-- Quote Text --}}
                {{-- PENTING: Pakai {!! !!} agar HTML dan Emoji dari RichEditor render dengan benar --}}
                <div class="text-xl italic font-medium text-gray-900 dark:text-white leading-relaxed prose dark:prose-invert max-w-none">
                    {!! $quote->quote_text !!}
                </div>
                
                {{-- Author & Date (Dibuat kecil dan abu-abu seperti contoh) --}}
                <div class="mt-6 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                    <span>—</span>
                    <span class="font-semibold">
                        {{ $quote->user->name }}
                    </span>
                    <span>({{ $quote->created_at->format('d M Y, H:i') }})</span>
                </div>
            </div>

            {{-- Action Buttons Footer --}}
            <div class="mt-4 flex items-center justify-between border-t border-gray-100 dark:border-white/5 pt-4">
                {{ $this->addQuoteAction }}

                @if($quote->user_id === auth()->id())
                    {{ $this->editQuoteAction }}
                @endif
            </div>
        @else
            {{-- Tampilan jika belum ada quote --}}
            <div class="text-center py-8 flex flex-col items-center justify-center h-full text-gray-500 italic gap-4">
                <p>Belum ada quotes untuk ditampilkan hari ini.</p>
                {{ $this->addQuoteAction }}
            </div>
        @endif
    </x-filament::section>

    {{-- Container untuk Modal --}}
    <x-filament-actions::modals />
</x-filament-widgets::widget>