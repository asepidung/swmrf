{{--
    Menyimpan dan memulihkan posisi scroll di halaman edit Costing.

    Setiap harga yang diketik menghitung ulang costing lalu memuat ulang halaman; dengan ratusan
    produk, kembali ke atas setiap kali tidak bisa dipakai. Posisi dicatat saat sebuah isian
    berubah, lalu dipulihkan (dengan beberapa percobaan, karena tabel produk dimuat belakangan)
    begitu halaman terbuka lagi dalam 20 detik. Murni sisi browser; bila sessionStorage tidak
    tersedia, perilakunya kembali seperti semula.
--}}
<script>
    (() => {
        const KEY = 'costingScroll';
        const save = () => {
            try {
                sessionStorage.setItem(KEY, JSON.stringify({ y: window.scrollY, t: Date.now(), p: location.pathname }));
            } catch (e) {}
        };

        document.addEventListener('change', save, true);

        let saved = null;
        try {
            saved = JSON.parse(sessionStorage.getItem(KEY) || 'null');
        } catch (e) {}

        if (saved && saved.p === location.pathname && Date.now() - saved.t < 20000) {
            let tries = 0;
            const timer = setInterval(() => {
                window.scrollTo(0, saved.y);
                if (Math.abs(window.scrollY - saved.y) < 2 || ++tries > 30) {
                    clearInterval(timer);
                    try { sessionStorage.removeItem(KEY); } catch (e) {}
                }
            }, 100);
        }
    })();
</script>
