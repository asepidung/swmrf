{{--
    Ikon tanda tanya di Ringkasan Costing: Filament menaruhnya di ujung kanan baris label, jauh dari
    labelnya. Di sini didekatkan ke label dan dikecilkan, supaya jelas milik label yang mana.
    Hanya dimuat di halaman edit dan lihat Costing.
--}}
<style>
    div:has(> .fi-fo-field-wrp-hint) {
        justify-content: flex-start !important;
        gap: 0.375rem !important;
    }
    .fi-fo-field-wrp-hint {
        margin-inline-start: 0 !important;
    }
    .fi-fo-field-wrp-hint-icon {
        width: 0.9rem !important;
        height: 0.9rem !important;
        cursor: help;
    }
</style>
