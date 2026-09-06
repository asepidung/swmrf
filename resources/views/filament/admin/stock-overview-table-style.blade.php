{{--
    Tampilan tabel Stock Overview.

    Aturan-aturan ini dulu berada DI DALAM salinan view tabel Filament
    (`beef-stock/table.blade.php`), 105 baris dari 327 baris yang membedakan
    salinan itu dengan aslinya. Padahal tidak satu pun di antaranya butuh
    salinan: semuanya CSS biasa.

    Memindahkannya ke sini membuat sisa salinan itu berisi PERUBAHAN STRUKTUR
    saja -- colspan header grup, lebar kolom, tombol collapse. Waktu Filament
    naik versi nanti, yang harus dibandingkan tinggal itu, bukan seratus baris
    gaya yang sebenarnya tidak ada urusannya dengan versi Filament.

    SEMUA aturan dibatasi pada `.fi-resource-beef-stocks` -- kelas yang
    dipasang Filament sendiri di halaman daftar sebuah Resource, dari slug-nya.
    Tanpa pembatas itu, aturan seperti `.fi-ta-table td { padding: 0.25rem }`
    akan mengubah SETIAP tabel di aplikasi ini. Dulu ia tidak perlu pembatas
    karena hidup di dalam view yang cuma dipakai satu halaman; sekarang ia
    dimuat di setiap halaman, jadi pembatasnya yang menggantikan peran itu.

    Dimuat di HEAD_END supaya berada sesudah stylesheet Filament.
--}}
<style>
    /* Baris dirapatkan: satu layar memuat lebih banyak barang. */
    .fi-resource-beef-stocks .fi-ta-table {
        line-height: 1rem !important;
    }

    .fi-resource-beef-stocks .fi-ta-table tbody tr {
        height: 32px !important;
    }

    .fi-resource-beef-stocks .fi-ta-cell > div,
    .fi-resource-beef-stocks .fi-ta-text,
    .fi-resource-beef-stocks .fi-ta-text-item {
        padding: 0 !important;
        margin: 0 !important;
    }

    /* Judul kolom di tengah dan huruf besar. */
    .fi-resource-beef-stocks .fi-ta-header-cell-label {
        justify-content: center !important;
        text-align: center !important;
        text-transform: uppercase !important;
    }

    .fi-resource-beef-stocks .fi-table-header-group-cell span {
        text-align: center !important;
        text-transform: uppercase !important;
        display: block !important;
        /* Seukuran isi tabel yang rapat; bawaannya text-sm. */
        font-size: 0.75rem !important;
        line-height: 1rem !important;
    }

    .fi-resource-beef-stocks .fi-ta-table thead th {
        text-align: center !important;
        text-transform: uppercase !important;
    }

    /* Padding sel dan garis pemisah antar kolom. */
    .fi-resource-beef-stocks .fi-ta-table th,
    .fi-resource-beef-stocks .fi-ta-table td {
        padding-top: 0.25rem !important;
        padding-bottom: 0.25rem !important;
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
        border-left: 1px solid rgb(229 231 235) !important;
        border-right: 1px solid rgb(229 231 235) !important;
    }

    .dark .fi-resource-beef-stocks .fi-ta-table th,
    .dark .fi-resource-beef-stocks .fi-ta-table td {
        border-color: rgb(63 63 70) !important;
    }

    /*
     * Garis bawaan antar baris header dibuang.
     *
     * Header tabel ini bertingkat -- nama gudang di atas, grade di bawahnya --
     * dan sel yang ber-rowspan terpotong garis itu di tengah-tengah.
     */
    .fi-resource-beef-stocks .fi-ta-table thead,
    .fi-resource-beef-stocks .fi-ta-table thead > tr {
        border-top: none !important;
        border-bottom: none !important;
        background-color: transparent !important;
    }

    .fi-resource-beef-stocks .fi-ta-table thead > tr:not(:first-child) {
        border-top: none !important;
    }

    .fi-resource-beef-stocks .fi-ta-table thead tr:not(:first-child) th {
        border-top: none !important;
    }

    .fi-resource-beef-stocks .fi-ta-table thead th {
        border-bottom: none !important;
    }

    /* Garisnya dikembalikan hanya di bawah baris nama gudang. */
    .fi-resource-beef-stocks .fi-table-header-group-cell {
        border-bottom: 1px solid rgb(229 231 235) !important;
    }

    .dark .fi-resource-beef-stocks .fi-table-header-group-cell {
        border-bottom: 1px solid rgb(63 63 70) !important;
    }

    /* Isi sel header ikut di tengah, apa pun bentuk pembungkusnya. */
    .fi-resource-beef-stocks .fi-ta-table th .fi-ta-header-cell-label,
    .fi-resource-beef-stocks .fi-ta-table th > button,
    .fi-resource-beef-stocks .fi-ta-table th > div,
    .fi-resource-beef-stocks .fi-ta-table .fi-ta-header-cell > button,
    .fi-resource-beef-stocks .fi-ta-table .fi-ta-header-cell > div {
        justify-content: center !important;
        align-items: center !important;
        text-align: center !important;
    }

    .fi-resource-beef-stocks .fi-ta-table .fi-ta-header-cell-label {
        text-align: center !important;
        justify-content: center !important;
        width: 100% !important;
        display: flex !important;
    }
</style>
