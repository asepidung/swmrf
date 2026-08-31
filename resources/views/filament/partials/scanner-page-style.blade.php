{{--
    Gaya halaman pemindaian: sidebar disembunyikan, tabel dipadatkan.

    Halaman-halaman ini dipakai dengan SATU ALAT PEMINDAI di tangan, bukan
    tetikus dan papan ketik. Setiap gulir mendatar berarti operator harus
    meletakkan pemindainya dulu, jadi yang perlu dilihat wajib muat tanpa
    digeser.

    Barcode yang 26 karakter itu tidak bisa dipatahkan, sehingga tabelnya
    menuntut lebar yang besar. Dua hal dikerjakan bersamaan di sini:
    sidebar dilepas untuk menambah ruang, dan tinggi baris serta ukuran
    huruf tabel dipadatkan supaya isinya benar-benar muat.

    Sidebar disembunyikan lewat CSS BERLINGKUP HALAMAN, bukan dengan
    mengubah keadaan sidebar milik Filament. Keadaan itu diingat antar
    halaman, jadi menutupnya dari sini akan ikut menutupnya di seluruh
    aplikasi -- dan pengguna akan menemukannya terkatup di tempat yang
    tidak pernah ia minta.

    Pola ini sudah lebih dulu dipakai di halaman Scan dan Labeling GR
    Product serta Labeling Boning; keduanya masih memuat salinannya
    sendiri. Kalau salah satunya disentuh lagi, arahkan ke berkas ini.
--}}
<style>
    /* Auto hide sidebar and expand main content on this page */
    aside.fi-sidebar {
        display: none !important;
    }
    .fi-main-ctn {
        padding-inline-start: 0 !important;
    }
    :root {
        --sidebar-width: 0px !important;
        --collapsed-sidebar-width: 0px !important;
    }

    table.fi-ta-table th,
    table.fi-ta-table tbody td {
        padding-top: 0.25rem !important;
        padding-bottom: 0.25rem !important;
        padding-left: 8px !important;
        padding-right: 8px !important;
        height: auto !important;
    }

    table.fi-ta-table tbody td>div,
    table.fi-ta-table tbody td>div>div,
    table.fi-ta-table tbody td>div>div>div {
        padding-top: 2px !important;
        padding-bottom: 2px !important;
        min-height: unset !important;
        line-height: 1.2 !important;
        gap: 0 !important;
    }

    .fi-ta-text,
    .fi-ta-text-item,
    .fi-ta-text-item-label {
        margin-top: 0 !important;
        margin-bottom: 0 !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
        line-height: 1.1 !important;
        font-size: 13px !important;
        white-space: nowrap !important;
        letter-spacing: -0.1px !important;
    }

    .fi-badge {
        padding: 2px 6px !important;
        min-height: 18px !important;
        line-height: 18px !important;
        font-size: 11px !important;
    }

    .fi-ta-actions {
        gap: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        justify-content: center !important;
    }

    .fi-ta-actions button {
        padding: 4px !important;
        min-height: 24px !important;
        height: 24px !important;
        width: 24px !important;
        margin: 0 !important;
    }

    .fi-ta-actions button svg {
        width: 16px !important;
        height: 16px !important;
    }
</style>
