<div
    x-data="{ balances: @js($balances) }"
    x-show="selectedRecords.length"
    x-cloak
    class="flex flex-wrap items-center gap-x-6 gap-y-1 px-4 py-3 text-sm sm:px-6"
    style="background: rgba(var(--primary-500), .10);"
>
    <span class="font-semibold">
        <span x-text="selectedRecords.length"></span> {{ __('invoices ticked') }}
    </span>
    <span class="text-base font-bold">
        {{ __('Total to pay') }}:
        Rp <span x-text="new Intl.NumberFormat('id-ID').format(selectedRecords.reduce((sum, id) => sum + (balances[id] || 0), 0))"></span>
    </span>
</div>
