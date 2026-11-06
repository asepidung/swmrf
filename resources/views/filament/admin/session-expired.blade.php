{{--
    Pengganti kotak bawaan peramban ("This page has expired. Would you like to refresh the page?").

    Livewire memanggil confirm() bawaan peramban begitu servernya menjawab 419 (sesi habis atau token kedaluwarsa).
    Di sini jawaban 419 ditangkap lebih dulu dan diganti kotak kita sendiri, dalam bahasa Indonesia, dengan tombol
    Masuk lagi. Halaman TIDAK dimuat ulang otomatis: isian yang belum disimpan masih terlihat, jadi bisa disalin dulu.
--}}
<style>
    #sesi-habis { position: fixed; inset: 0; z-index: 99999; display: none; align-items: center; justify-content: center;
        padding: 1.25rem; background: rgba(8, 10, 15, .72); backdrop-filter: blur(3px); }
    #sesi-habis.tampil { display: flex; }
    #sesi-habis .kartu { width: 100%; max-width: 24rem; text-align: center; padding: 2rem 1.5rem 1.5rem;
        background: #151a23; color: #e5e7eb; border: 1px solid rgba(255,255,255,.08); border-top: 5px solid #38bdf8;
        border-radius: .9rem; box-shadow: 0 20px 50px rgba(0,0,0,.5);
        font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
    #sesi-habis .jam { width: 3.5rem; height: 3.5rem; margin: 0 auto .9rem; color: #7dd3fc; animation: sesi-putar 12s linear infinite; }
    #sesi-habis h2 { margin: 0 0 .5rem; font-size: 1.2rem; font-weight: 700; }
    #sesi-habis p { margin: 0 0 1.4rem; color: #9ca3af; line-height: 1.55; font-size: .92rem; }
    #sesi-habis .tombol { display: block; width: 100%; box-sizing: border-box; padding: .7rem 1rem; border: 0; border-radius: .55rem;
        background: #38bdf8; color: #0b1220; font-weight: 700; font-size: .95rem; text-decoration: none; cursor: pointer; }
    #sesi-habis .tombol:hover { background: #0ea5e9; }
    #sesi-habis .tutup { margin-top: .75rem; background: none; border: 0; color: #9ca3af; font-size: .82rem; cursor: pointer; text-decoration: underline; }
    @keyframes sesi-putar { to { transform: rotate(360deg); } }
</style>

<div id="sesi-habis" role="alertdialog" aria-modal="true" aria-labelledby="sesi-habis-judul">
    <div class="kartu">
        <svg class="jam" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 2v20M4.2 7l15.6 10M4.2 17L19.8 7M9 3.5l3 2.5 3-2.5M9 20.5l3-2.5 3 2.5M2.8 9.5l3.7.8-.8 3.7M21.2 14.5l-3.7-.8.8-3.7M2.8 14.5l3.7-.8-.8-3.7M21.2 9.5l-3.7.8.8 3.7" />
        </svg>
        <h2 id="sesi-habis-judul">Sesi Anda masuk chiller dulu</h2>
        <p>Terlalu lama didiamkan, jadi sesi Anda disimpan di tempat dingin demi keamanan, seperti daging yang tidak boleh kelamaan di luar.
            Masuk lagi untuk melanjutkan. Isian yang belum disimpan belum terkirim ke server.</p>
        <a class="tombol" href="{{ route('filament.admin.auth.login') }}">Keluarkan dari chiller (masuk lagi)</a>
        <button type="button" class="tutup" onclick="document.getElementById('sesi-habis').classList.remove('tampil')">Tetap di halaman ini dulu</button>
    </div>
</div>

<script>
    (function () {
        function pasang() {
            window.Livewire.hook('request', function (ctx) {
                ctx.fail(function (e) {
                    if (e.status === 419) {
                        e.preventDefault();
                        document.getElementById('sesi-habis').classList.add('tampil');
                    }
                });
            });
        }
        if (window.Livewire && window.Livewire.hook) { pasang(); }
        else { document.addEventListener('livewire:init', pasang); }
    })();
</script>
