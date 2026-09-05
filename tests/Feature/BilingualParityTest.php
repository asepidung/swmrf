<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * `lang/id.json` dan `lang/en.json` wajib memuat kunci yang sama persis.
 *
 * Konvensi proyek ini: kunci ditulis dalam Bahasa Inggris, `en.json`
 * memetakannya ke dirinya sendiri, `id.json` ke terjemahan Indonesianya.
 * Kunci Inggris tetap didaftarkan meski nilainya sama dengan kuncinya sendiri
 * -- gunanya bukan menerjemahkan, melainkan supaya penyeragaman istilah nanti
 * cukup mengubah berkas bahasa tanpa menyentuh kode.
 *
 * Sebelum 28 Agustus 2026 ada 162 kunci yang hanya hidup di `id.json`.
 * Gejalanya tidak pernah terlihat sebagai error: Laravel menampilkan kuncinya
 * sendiri saat terjemahan tidak ditemukan, jadi teksnya tetap muncul -- hanya
 * saja dalam bahasa yang salah, dan tidak ada yang menyadarinya.
 *
 * Test ini menjaga invarian itu untuk SELURUH aplikasi, bukan per modul,
 * karena lubangnya memang muncul di mana saja.
 */
class BilingualParityTest extends TestCase
{
    /** @return array<string, mixed> */
    protected function strings(string $locale): array
    {
        $path = lang_path($locale . '.json');

        $this->assertFileExists($path);

        $decoded = json_decode(file_get_contents($path), true);

        $this->assertIsArray($decoded, "lang/{$locale}.json bukan JSON yang valid.");

        return $decoded;
    }

    /** @test */
    public function every_indonesian_key_also_exists_in_english()
    {
        $missing = array_keys(array_diff_key($this->strings('id'), $this->strings('en')));

        sort($missing);

        $this->assertSame(
            [],
            $missing,
            "Kunci berikut ada di id.json tapi tidak di en.json:\n" . implode("\n", $missing),
        );
    }

    /** @test */
    public function every_english_key_also_exists_in_indonesian()
    {
        $missing = array_keys(array_diff_key($this->strings('en'), $this->strings('id')));

        sort($missing);

        $this->assertSame(
            [],
            $missing,
            "Kunci berikut ada di en.json tapi tidak di id.json:\n" . implode("\n", $missing),
        );
    }

    /**
     * Kata-kata yang hampir pasti Bahasa Indonesia dan tidak dipakai sebagai
     * istilah teknis di aplikasi ini.
     *
     * "Opname" SENGAJA tidak masuk daftar -- itu istilah baku yang dipakai
     * dalam kedua bahasa di lingkungan kerja ini. Begitu juga "total", "data",
     * "label", "detail", dan "final": bentuknya sama di kedua bahasa, jadi
     * memasukkannya hanya menghasilkan tuduhan palsu.
     *
     * **Daftarnya dilebarkan dari 34 menjadi 190 kata pada 5 September 2026.**
     * Sebelumnya penjaga ini melewatkan kunci Indonesia yang kebetulan tidak
     * memuat satu pun dari 34 kata itu -- `Tampilkan Exp Date di Label?`,
     * `Kunci Repack (Final)`, `Stok Aktual`, `Periode Awal`, dan puluhan
     * lainnya. Registernya mencatat 64 kunci, padahal yang sebenarnya ada 127.
     *
     * Angka yang salah lebih berbahaya daripada tidak ada angka sama sekali:
     * 64 terbaca sebagai utang yang terukur dan hampir habis, sementara 63
     * sisanya tidak terlihat oleh siapa pun.
     *
     * @return array<int, string>
     */
    protected function indonesianWords(): array
    {
        return [
            'yang', 'dan', 'atau', 'tidak', 'sudah', 'belum', 'akan', 'dari',
            'untuk', 'dengan', 'jika', 'bila', 'nomor', 'segel', 'buat',
            'berhasil', 'gagal', 'silakan', 'mohon', 'semua', 'catatan',
            'temuan', 'opsional', 'berat', 'fisik', 'selisih', 'selesaikan',
            'biaya', 'harga', 'satuan', 'karton', 'ongkir', 'umur', 'armada',
            'adalah', 'ini', 'itu', 'ada', 'tambah', 'tambahkan', 'hapus',
            'ubah', 'simpan', 'batal', 'batalkan', 'kirim', 'terima', 'barang',
            'gudang', 'stok', 'jumlah', 'tanggal', 'tgl', 'pilih', 'masukkan',
            'isi', 'wajib', 'sedang', 'pada', 'oleh', 'anda', 'kembali',
            'lagi', 'bisa', 'boleh', 'harus', 'juga', 'saat', 'agar', 'supaya',
            'sisa', 'nilai', 'cetak', 'lihat', 'buka', 'kunci', 'bahan',
            'hasil', 'kosong', 'penuh', 'masuk', 'keluar', 'dipakai',
            'terpakai', 'dibuat', 'dihapus', 'diubah', 'disimpan', 'dikirim',
            'diterima', 'dikunci', 'ditemukan', 'tersedia', 'habis', 'banyak',
            'sedikit', 'lebih', 'kurang', 'setiap', 'tiap', 'antara',
            'sebelum', 'sesudah', 'setelah', 'selama', 'hingga', 'sampai',
            'sejak', 'karena', 'sehingga', 'tolong', 'perhatian', 'peringatan',
            'aturan', 'pengguna', 'pelanggan', 'pemasok', 'penjualan',
            'pembelian', 'penerimaan', 'pengiriman', 'pengembalian',
            'potongan', 'pembayaran', 'piutang', 'utang', 'hutang', 'tagihan',
            'faktur', 'kwitansi', 'surat', 'jalan', 'jalur', 'baris', 'kolom',
            'proses', 'diproses', 'selesai', 'dipotong', 'ekor', 'sapi',
            'daging', 'tulang', 'kulit', 'buntut', 'jeroan', 'susut',
            'penyusutan', 'timbang', 'timbangan', 'ditimbang', 'awal', 'akhir',
            'mulai', 'berakhir', 'tutup', 'ditutup', 'dibuka', 'aktif',
            'nonaktif', 'lokasi', 'tujuan', 'asal', 'sumber', 'keterangan',
            'alasan', 'tampilkan', 'sembunyikan', 'discan', 'terscan',
            'histori', 'riwayat', 'ringkasan', 'nama', 'daftar', 'urutan',
            'satu', 'dua', 'tiga', 'atas', 'bawah', 'dalam', 'luar', 'lain',
            'lainnya', 'sendiri', 'punya', 'milik', 'kembalikan', 'ditolak',
            'tolakan', 'setujui', 'persetujuan', 'disetujui', 'menghapus',
            'membuat', 'mengubah', 'menyimpan', 'mengunci', 'yakin',
            // Ditambahkan 5 September 2026: `Periode`, `Periode
            // (Bulan/Tahun)`, dan `Produk` lolos daftar sebelumnya
            // karena tidak memuat satu pun kata di dalamnya.
            'periode', 'bulan', 'tahun', 'produk', 'terduga', 'cocok',
            // Ditambahkan 6 September 2026: `Sesuai` lolos daftar
            // sebelumnya, dan lolosnya baru ketahuan setelah kunci itu
            // dihapus dari berkas bahasa sementara kodenya masih
            // memakainya -- kunci yatim yang tampil apa adanya.
            'sesuai', 'terhitung', 'hitung', 'satuan', 'utuh',
            'hitungan', 'menghitung', 'dipindai', 'pindai',
        ];
    }

    /**
     * Setiap kunci `__('...')` yang benar-benar ditulis di kode.
     *
     * Memindai KODE, bukan hanya berkas terjemahan. Ini lubang yang membuat
     * seluruh modul Receivable lolos: kuncinya berbahasa Indonesia DAN tidak
     * pernah didaftarkan di `id.json` sama sekali, jadi penjaga yang hanya
     * membaca `id.json` tidak pernah melihatnya.
     *
     * Yang tidak terdaftar justru yang paling buruk. Laravel menampilkan
     * kuncinya sendiri saat terjemahan tidak ada, sehingga pengguna yang
     * memilih bahasa Inggris melihat kalimat Indonesia utuh -- tanpa satu pun
     * gejala bahwa ada yang salah.
     *
     * @return array<int, string>
     */
    protected function keysWrittenInCode(): array
    {
        $keys = [];

        foreach (['app', 'resources/views'] as $root) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($root))
            );

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $isi = file_get_contents($file->getPathname());

                // KOMENTAR DIBUANG LEBIH DULU.
                //
                // Penjaga ini sudah tiga kali menuduh komentar yang justru
                // MENJELASKAN kunci lama yang sedang dibuang. Menulis
                // `__('Sesuai')` di dalam komentar untuk menerangkan apa yang
                // diperbaiki membuat penjaganya berteriak, dan jalan keluar
                // yang gampang -- mengaburkan komentarnya -- justru membuang
                // keterangan yang paling berguna.
                //
                // Berkas Blade tidak bisa dibaca sebagai token PHP (isi
                // `{{ }}` terbaca sebagai HTML biasa), jadi komentarnya
                // dibuang dengan pola.
                if (str_ends_with($file->getFilename(), '.blade.php')) {
                    $isi = preg_replace(['/\{\{--.*?--\}\}/s', '/<!--.*?-->/s'], ' ', $isi);
                } else {
                    $isi = $this->tanpaKomentar($isi);
                }

                preg_match_all("/__\('([^']+)'/", $isi, $matches);

                foreach ($matches[1] as $key) {
                    $keys[$key] = true;
                }
            }
        }

        return array_keys($keys);
    }

    /** Isi berkas PHP tanpa komentarnya, disusun ulang dari tokennya. */
    protected function tanpaKomentar(string $isi): string
    {
        $hasil = '';

        foreach (@token_get_all($isi) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $hasil .= is_array($token) ? $token[1] : $token;
        }

        return $hasil;
    }

    /** @return array<int, string> */
    protected function indonesianKeys(): array
    {
        $words = $this->indonesianWords();
        $offenders = [];

        $candidates = array_unique(array_merge(
            array_keys($this->strings('id')),
            $this->keysWrittenInCode(),
        ));

        foreach ($candidates as $key) {
            preg_match_all('/[A-Za-z]+/', mb_strtolower($key), $matches);

            foreach ($matches[0] as $word) {
                if (in_array($word, $words, true)) {
                    $offenders[] = $key;
                    break;
                }
            }
        }

        sort($offenders);

        return $offenders;
    }

    /**
     * Utang yang sudah ada boleh tinggal, tapi TIDAK BOLEH BERTAMBAH.
     *
     * Menulis kunci dalam Bahasa Indonesia membuat pengguna yang memilih
     * bahasa Inggris tetap melihat teks Indonesia, karena Laravel menampilkan
     * kuncinya sendiri saat terjemahan tidak ditemukan. Mendaftarkannya di
     * `en.json` apa adanya tidak memperbaiki apa pun -- justru melanggengkan
     * masalahnya. Yang benar: ganti kuncinya di KODE menjadi Bahasa Inggris,
     * lalu daftarkan terjemahan Indonesianya di `id.json`.
     *
     * Daftarnya dicatat di `tests/Fixtures/indonesian-translation-keys.json`
     * sebagai register utang yang terlihat, tersebar sampai ke modul yang
     * belum disisir (Repack, Sales Return, Cattle Weighing, dan lain-lain).
     *
     * **Angkanya naik dari 41 menjadi 75 pada 3 September 2026, dan itu bukan
     * berarti utangnya bertambah.** Sampai hari itu penjaga ini hanya membaca
     * `id.json`, sehingga kunci Indonesia yang TIDAK PERNAH DIDAFTARKAN lolos
     * sepenuhnya -- padahal justru itu yang paling buruk, karena Laravel
     * menampilkan kuncinya sendiri dan pengguna berbahasa Inggris melihat
     * kalimat Indonesia utuh. Seluruh modul Receivable lolos dengan cara itu.
     * Sejak sekarang kodenya ikut dipindai, dan 34 kunci yang selama ini tidak
     * terlihat masuk ke register.
     *
     * **Registernya KOSONG sejak 5 September 2026 (#267).** Ke-127 kunci
     * Indonesia sudah diganti menjadi Bahasa Inggris, jadi berkas baseline-nya
     * kini `[]` dan test ini tidak lagi mentoleransi apa pun. Kalau nanti ada
     * baris yang muncul lagi di sana, itu tanda utangnya kembali -- bukan
     * daftar tugas yang boleh didiamkan.
     *
     * @test
     */
    public function no_new_indonesian_translation_keys_are_introduced()
    {
        $baseline = json_decode(
            file_get_contents(base_path('tests/Fixtures/indonesian-translation-keys.json')),
            true,
        );

        $this->assertIsArray($baseline, 'Berkas baseline kunci Indonesia rusak.');

        $current = $this->indonesianKeys();

        $new = array_values(array_diff($current, $baseline));
        $fixed = array_values(array_diff($baseline, $current));

        $this->assertSame(
            [],
            $new,
            "Kunci berikut ditulis dalam Bahasa Indonesia. Ganti kuncinya di KODE menjadi "
            . "Bahasa Inggris, lalu daftarkan terjemahan Indonesianya di id.json:\n"
            . implode("\n", $new),
        );

        $this->assertSame(
            [],
            $fixed,
            "Bagus, kunci berikut sudah dibereskan. Hapus juga barisnya dari "
            . "tests/Fixtures/indonesian-translation-keys.json supaya utangnya tidak bisa kembali:\n"
            . implode("\n", $fixed),
        );
    }

    /**
     * Berkas yang teksnya SENGAJA berbahasa Indonesia.
     *
     * Dua kelompok, dua alasan yang berbeda, dan keduanya keputusan Owner
     * 5 September 2026. Lihat keterangan per baris di bawah.
     *
     * @return array<int, string>
     */
    protected function sengajaIndonesia(): array
    {
        return [
            // Dokumen cetak dan ekspor PDF. Bahasa sebuah dokumen ditentukan
            // oleh siapa yang MEMBACANYA, bukan oleh setelan operator yang
            // menekan tombol cetak.
            'resources/views/print/',
            'resources/views/exports/',
            // Dokumen cetak yang kebetulan tinggal di folder resource-nya.
            'pages/print-carcass.blade.php',

            // Perintah artisan dan keluarannya. Pembacanya yang MERAWAT
            // sistem, bukan pengguna aplikasi -- keputusan yang sama dengan
            // baris log.
            'app/Console/',

            '/vendor/',
        ];
    }

    /**
     * Teks Indonesia yang ditulis LANGSUNG di kode, tanpa `__()`.
     *
     * Ratchet di atas hanya mengawasi KUNCI `__('...')`. Bentuk yang jauh
     * lebih sering muncul justru tidak berbentuk kunci sama sekali:
     *
     *     ->label('Alasan Pengembalian')
     *     ->modalHeading('PERINGATAN KERAS: Finalisasi Opname!')
     *
     * Itu bukan kunci terjemahan, jadi ia lolos dari penjaga mana pun dan
     * teksnya tidak akan pernah berubah bahasa. Ada 21 yang seperti ini saat
     * #267 dikerjakan, tersebar sampai ke modul yang sudah dinyatakan beres.
     *
     * @test
     */
    public function no_indonesian_text_is_written_straight_into_the_code()
    {
        $methods = [
            'label', 'title', 'placeholder', 'helperText', 'description',
            'tooltip', 'modalHeading', 'modalDescription',
            'modalSubmitActionLabel', 'modalCancelActionLabel',
            'successNotificationTitle', 'failureNotificationTitle', 'body',
            'heading', 'subheading', 'emptyStateHeading',
            'emptyStateDescription', 'navigationLabel', 'hint', 'trueLabel',
            'falseLabel', 'badge',
        ];

        $pattern = "/->(" . implode('|', $methods) . ")\(\s*'((?:[^'\\\\]|\\\\.)+)'\s*\)/";

        $offenders = [];

        foreach ($this->phpFiles(['app']) as $file) {
            preg_match_all($pattern, file_get_contents($file), $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                if ($this->looksIndonesian($match[2])) {
                    $offenders[] = $this->relative($file) . " -> {$match[1]}('{$match[2]}')";
                }
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Teks berikut ditulis langsung di kode, jadi bahasanya tidak akan pernah "
            . "berubah. Bungkus dengan __() dan daftarkan terjemahannya di id.json:\n"
            . implode("\n", $offenders),
        );
    }

    /**
     * Teks Indonesia mentah di Blade halaman aplikasi.
     *
     * Komentar dibuang lebih dulu: komentar di proyek ini memang sengaja
     * berbahasa Indonesia, dan tanpa membuangnya penjaga ini hanya
     * menghasilkan tuduhan palsu. Begitu juga `@php`, `<style>`, dan
     * `<script>`.
     *
     * @test
     */
    public function no_indonesian_text_is_written_straight_into_the_screens()
    {
        $offenders = [];

        foreach ($this->phpFiles(['resources/views/filament']) as $file) {
            $content = file_get_contents($file);

            // Barisnya diganti baris kosong sebanyak isinya, bukan dihapus,
            // supaya nomor baris yang dilaporkan tetap menunjuk tempat yang
            // benar di berkas aslinya.
            foreach ([
                '/\{\{--.*?--\}\}/s', '/<!--.*?-->/s', '/@php.*?@endphp/s',
                '/<style\b.*?<\/style>/si', '/<script\b.*?<\/script>/si',
            ] as $buang) {
                $content = preg_replace_callback(
                    $buang,
                    fn (array $m): string => str_repeat("\n", substr_count($m[0], "\n")),
                    $content,
                );
            }

            foreach (explode("\n", $content) as $number => $line) {
                // `$record->kunci` nama KOLOM, bukan teks layar -- rantai
                // properti ikut dibuang, bukan hanya nama variabelnya.
                $bare = preg_replace(
                    [
                        '/\{\{.*?\}\}/', '/\{!!.*?!!\}/', '/<[^>]*>/',
                        '/@\w+/', '/\$\w+(\s*(->|\?->)\s*\w+)*/', '~//.*$~',
                    ],
                    ' ',
                    $line,
                );

                if ($this->looksIndonesian($bare)) {
                    $offenders[] = $this->relative($file) . ':' . ($number + 1) . '  ' . trim($line);
                }
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Teks berikut tampil di layar tetapi ditulis langsung di Blade. "
            . "Bungkus dengan {{ __('...') }}:\n" . implode("\n", $offenders),
        );
    }

    /**
     * Teks Indonesia di dalam string BERINTERPOLASI, di luar `__()`.
     *
     * Bentuk inilah yang meloloskan dua kalimat dari `FoundItemScanner`:
     *
     *     $historyMessage = "Histori Barang ditemukan. Posisi Terakhir di
     *                        Gudang {$warehouseName} (Proses: {$type}).";
     *
     * Penjaga hardcode yang lain hanya memeriksa ARGUMEN pemanggilan seperti
     * `->label('...')`. Teks yang ditugaskan ke variabel lalu dipakai
     * belakangan tidak pernah dilihatnya, padahal ia sama-sama tampil di
     * layar.
     *
     * Token `T_ENCAPSED_AND_WHITESPACE` hanya muncul di dalam kutip ganda yang
     * memuat variabel, dan tidak pernah dihasilkan oleh komentar -- jadi
     * penjaga ini tidak bisa menuduh keterangannya sendiri.
     *
     * @test
     */
    public function no_indonesian_text_hides_inside_an_interpolated_string()
    {
        $offenders = [];

        foreach ($this->phpFiles(['app']) as $file) {
            foreach (@token_get_all(file_get_contents($file)) as $token) {
                if (! is_array($token) || $token[0] !== T_ENCAPSED_AND_WHITESPACE) {
                    continue;
                }

                if ($this->looksIndonesian($token[1])) {
                    $offenders[] = $this->relative($file).':'.$token[2].'  '.trim($token[1]);
                }
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Teks berikut tampil di layar tetapi bersembunyi di dalam string "
            . "berinterpolasi. Bungkus dengan __() dan pakai penampung:
"
            . implode("
", $offenders),
        );
    }

    /**
     * Setiap kunci yang dipakai kode HARUS terdaftar di kedua berkas bahasa.
     *
     * Sampai 6 September 2026 ada 161 kunci yang tidak pernah terdaftar. Untuk
     * pengguna berbahasa Inggris tidak ada yang rusak -- Laravel menampilkan
     * kuncinya sendiri, dan kuncinya memang Bahasa Inggris. Yang tidak
     * kebagian justru pengguna INDONESIA: teks itu tidak pernah bisa
     * diterjemahkan, dan tidak ada satu pun gejala yang memberitahu.
     *
     * Penjaga bahasa yang lain tidak menangkapnya karena semuanya mencari
     * teks berbahasa INDONESIA. Teks Inggris yang tidak diterjemahkan lolos
     * seluruhnya.
     *
     * Sesudah 161 kunci itu didaftarkan, sisanya nol -- jadi penjaga ini
     * keras sejak awal, tanpa daftar toleransi.
     *
     * @test
     */
    public function every_key_used_in_code_is_registered()
    {
        $en = $this->strings('en');
        $hilang = [];

        foreach ($this->keysWrittenInCode() as $kunci) {
            // Kunci milik paket lain (`filament-tables::...`) diterjemahkan
            // oleh paketnya sendiri, bukan oleh berkas bahasa aplikasi ini.
            if (str_contains($kunci, '::')) {
                continue;
            }

            if (! array_key_exists($kunci, $en)) {
                $hilang[] = $kunci;
            }
        }

        sort($hilang);

        $this->assertSame(
            [],
            $hilang,
            "Kunci berikut dipakai kode tetapi tidak terdaftar di lang/en.json, "
            . "sehingga tidak akan pernah bisa diterjemahkan:
" . implode("
", $hilang),
        );
    }

    protected function looksIndonesian(string $text): bool
    {
        $words = $this->indonesianWords();

        preg_match_all('/[A-Za-z]+/', mb_strtolower($text), $matches);

        foreach ($matches[0] as $word) {
            if (in_array($word, $words, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $roots
     * @return \Generator<string>
     */
    protected function phpFiles(array $roots): \Generator
    {
        foreach ($roots as $root) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($root))
            );

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());

                foreach ($this->sengajaIndonesia() as $dikecualikan) {
                    if (str_contains($path, $dikecualikan)) {
                        continue 2;
                    }
                }

                yield $file->getPathname();
            }
        }
    }

    protected function relative(string $path): string
    {
        return str_replace(['\\', base_path() . '/'], ['/', ''], $path);
    }
}
