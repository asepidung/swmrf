<?php

return [
    /*
     * Modul keuangan ditutup untuk semua orang, termasuk admin (Owner, 2 November 2026):
     * Bank Accounts, Payables, Receivables, Cash Book, dan Sales Report hilang dari sidebar
     * dan halamannya tertolak. Izin dan data tidak diubah. Buka kembali dengan
     * MODUL_KEUANGAN_DITUTUP=false di .env lalu config:cache.
     */
    'keuangan_ditutup' => (bool) env('MODUL_KEUANGAN_DITUTUP', true),
];
