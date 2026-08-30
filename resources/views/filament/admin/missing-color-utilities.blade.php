{{--
    Kelas warna yang DIPAKAI aplikasi ini tapi tidak ada CSS-nya.

    Filament hanya menyertakan kelas utilitas yang dipakai kode Filament
    sendiri. Proyek ini tidak mengompilasi tema Filament kustom, jadi kelas
    seperti `bg-warning-500`, `text-success-700`, `divide-y`, dan
    `bg-gradient-to-r` tidak menghasilkan CSS apa pun.

    Gejalanya tidak pernah terasa sebagai kerusakan: elemennya tetap ada dan
    tetap bisa diklik, hanya tidak berwarna -- dan garis pemisah tabel hilang
    tanpa ada yang menyadarinya. Tombol "Damaged Label" yang tampil polos
    adalah yang akhirnya membuatnya ketahuan.

    KENAPA BUKAN TEMA FILAMENT KUSTOM. Itu memang jawaban yang lebih rapi,
    tetapi butuh `npm run build`, sementara server tidak punya node dan
    `public/build` tidak masuk repo. Artinya setiap perubahan tampilan jadi
    bergantung pada langkah build yang bisa terlupakan -- dan lupa build
    berarti perubahan tidak sampai TANPA GEJALA APA PUN. Itu persis jenis
    kegagalan yang berulang di proyek ini, jadi sengaja dihindari.

    Filament sudah menyuntikkan variabel warnanya (`--warning-500`,
    `--success-50`, dan seterusnya) ke setiap halaman panel, jadi aturan di
    bawah tetap mengikuti palet dan tema gelap tanpa kompilasi apa pun.

    ISINYA DIBANGKITKAN DARI PEMINDAIAN BLADE, bukan ditulis dari ingatan.
    `MissingColorUtilitiesTest` memindai ulang seluruh blade dan menggagalkan
    build bila ada kelas warna yang tidak tercakup -- termasuk kelas baru yang
    ditambahkan nanti. Jangan menambah baris di sini secara manual tanpa
    memastikan test itu ikut hijau.
--}}
<style>
    .bg-danger-50 { background-color: rgba(var(--danger-50),1); }
    .bg-danger-500 { background-color: rgba(var(--danger-500),1); }
    .bg-gradient-to-b { background-image: linear-gradient(to bottom, var(--tw-gradient-stops)); }
    .bg-gradient-to-br { background-image: linear-gradient(to bottom right, var(--tw-gradient-stops)); }
    .bg-gradient-to-r { background-image: linear-gradient(to right, var(--tw-gradient-stops)); }
    .bg-gradient-to-tr { background-image: linear-gradient(to top right, var(--tw-gradient-stops)); }
    .bg-gray-50\/50 { background-color: rgba(var(--gray-50),0.5); }
    .bg-gray-50\/80 { background-color: rgba(var(--gray-50),0.8); }
    .bg-gray-500 { background-color: rgba(var(--gray-500),1); }
    .bg-gray-700 { background-color: rgba(var(--gray-700),1); }
    .bg-gray-800 { background-color: rgba(var(--gray-800),1); }
    .bg-gray-900 { background-color: rgba(var(--gray-900),1); }
    .bg-primary-100 { background-color: rgba(var(--primary-100),1); }
    .bg-primary-500\/10 { background-color: rgba(var(--primary-500),0.1); }
    .bg-success-50 { background-color: rgba(var(--success-50),1); }
    .bg-success-500 { background-color: rgba(var(--success-500),1); }
    .bg-warning-50 { background-color: rgba(var(--warning-50),1); }
    .bg-warning-500 { background-color: rgba(var(--warning-500),1); }
    .border-danger-200 { border-color: rgba(var(--danger-200),1); }
    .border-gray-500 { border-color: rgba(var(--gray-500),1); }
    .border-primary-100 { border-color: rgba(var(--primary-100),1); }
    .border-success-200 { border-color: rgba(var(--success-200),1); }
    .border-warning-200 { border-color: rgba(var(--warning-200),1); }
    .dark\:bg-danger-900\/20:is(.dark *) { background-color: rgba(var(--danger-900),0.2); }
    .dark\:bg-gray-800\/50:is(.dark *) { background-color: rgba(var(--gray-800),0.5); }
    .dark\:bg-primary-500\/20:is(.dark *) { background-color: rgba(var(--primary-500),0.2); }
    .dark\:bg-primary-900\/30:is(.dark *) { background-color: rgba(var(--primary-900),0.3); }
    .dark\:bg-primary-950\/30:is(.dark *) { background-color: rgba(var(--primary-950),0.3); }
    .dark\:bg-success-900\/20:is(.dark *) { background-color: rgba(var(--success-900),0.2); }
    .dark\:bg-warning-900\/20:is(.dark *) { background-color: rgba(var(--warning-900),0.2); }
    .dark\:border-danger-800:is(.dark *) { border-color: rgba(var(--danger-800),1); }
    .dark\:border-gray-700\/50:is(.dark *) { border-color: rgba(var(--gray-700),0.5); }
    .dark\:border-gray-800:is(.dark *) { border-color: rgba(var(--gray-800),1); }
    .dark\:border-gray-800\/50:is(.dark *) { border-color: rgba(var(--gray-800),0.5); }
    .dark\:border-primary-900\/40:is(.dark *) { border-color: rgba(var(--primary-900),0.4); }
    .dark\:border-success-800:is(.dark *) { border-color: rgba(var(--success-800),1); }
    .dark\:border-warning-800:is(.dark *) { border-color: rgba(var(--warning-800),1); }
    .dark\:divide-gray-700 > :not([hidden]) ~ :not([hidden]):is(.dark *) { border-color: rgba(var(--gray-700),1); }
    .dark\:divide-gray-800 > :not([hidden]) ~ :not([hidden]):is(.dark *) { border-color: rgba(var(--gray-800),1); }
    .dark\:hover\:bg-gray-800\/10:hover:is(.dark *) { background-color: rgba(var(--gray-800),0.1); }
    .dark\:hover\:bg-gray-800\/50:hover:is(.dark *) { background-color: rgba(var(--gray-800),0.5); }
    .dark\:hover\:text-primary-300:hover:is(.dark *) { color: rgba(var(--primary-300),1); }
    .dark\:ring-primary-900\/10:is(.dark *) { --tw-ring-color: rgba(var(--primary-900),0.1); }
    .dark\:text-gray-100:is(.dark *) { color: rgba(var(--gray-100),1); }
    .dark\:text-info-500:is(.dark *) { color: rgba(var(--info-500),1); }
    .dark\:text-success-400:is(.dark *) { color: rgba(var(--success-400),1); }
    .dark\:text-warning-400:is(.dark *) { color: rgba(var(--warning-400),1); }
    .dark\:text-warning-500:is(.dark *) { color: rgba(var(--warning-500),1); }
    .dark\:via-gray-700:is(.dark *) { --tw-gradient-to: rgba(var(--gray-700),0) var(--tw-gradient-to-position); --tw-gradient-stops: var(--tw-gradient-from), rgba(var(--gray-700),1) var(--tw-gradient-via-position), var(--tw-gradient-to); }
    .focus\:border-primary-500:focus { border-color: rgba(var(--primary-500),1); }
    .focus\:ring-primary-500:focus { --tw-ring-color: rgba(var(--primary-500),1); }
    .from-primary-500\/20 { --tw-gradient-from: rgba(var(--primary-500),0.2) var(--tw-gradient-from-position); --tw-gradient-to: rgba(var(--primary-500),0) var(--tw-gradient-to-position); --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to); }
    .hover\:bg-gray-50\/50:hover { background-color: rgba(var(--gray-50),0.5); }
    .hover\:bg-gray-55:hover { background-color: rgba(var(--gray-50),1); }
    .hover\:bg-primary-500:hover { background-color: rgba(var(--primary-500),1); }
    .hover\:text-danger-500:hover { color: rgba(var(--danger-500),1); }
    .hover\:text-gray-600:hover { color: rgba(var(--gray-600),1); }
    .hover\:text-primary-500:hover { color: rgba(var(--primary-500),1); }
    .hover\:text-warning-500:hover { color: rgba(var(--warning-500),1); }
    .ring-primary-50 { --tw-ring-color: rgba(var(--primary-50),1); }
    .text-danger-400 { color: rgba(var(--danger-400),1); }
    .text-danger-700 { color: rgba(var(--danger-700),1); }
    .text-gray-300 { color: rgba(var(--gray-300),1); }
    .text-gray-800 { color: rgba(var(--gray-800),1); }
    .text-gray-900 { color: rgba(var(--gray-900),1); }
    .text-info-500 { color: rgba(var(--info-500),1); }
    .text-info-600 { color: rgba(var(--info-600),1); }
    .text-success-600 { color: rgba(var(--success-600),1); }
    .text-success-700 { color: rgba(var(--success-700),1); }
    .text-warning-400 { color: rgba(var(--warning-400),1); }
    .text-warning-500 { color: rgba(var(--warning-500),1); }
    .text-warning-600 { color: rgba(var(--warning-600),1); }
    .text-warning-700 { color: rgba(var(--warning-700),1); }
    .to-primary-500\/10 { --tw-gradient-to: rgba(var(--primary-500),0.1) var(--tw-gradient-to-position); }
    .via-gray-300 { --tw-gradient-to: rgba(var(--gray-300),0) var(--tw-gradient-to-position); --tw-gradient-stops: var(--tw-gradient-from), rgba(var(--gray-300),1) var(--tw-gradient-via-position), var(--tw-gradient-to); }
</style>
