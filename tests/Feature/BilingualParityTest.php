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
     * dalam kedua bahasa di lingkungan kerja ini.
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
        ];
    }

    /** @return array<int, string> */
    protected function indonesianKeys(): array
    {
        $words = $this->indonesianWords();
        $offenders = [];

        foreach (array_keys($this->strings('id')) as $key) {
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
     * Per 28 Agustus 2026 masih ada 43 kunci semacam itu, tersebar sampai ke
     * modul yang belum disisir (Repack, Sales Return, Cattle Weighing, dan
     * lain-lain). Membereskan semuanya pekerjaan tersendiri; daftarnya
     * dicatat di `tests/Fixtures/indonesian-translation-keys.json` sebagai
     * register utang yang terlihat.
     *
     * Test ini bersifat ratchet: kunci baru berbahasa Indonesia langsung
     * gagal, sementara yang lama dibiarkan sampai gilirannya disisir. Saat
     * ada yang dibereskan, hapus juga barisnya dari berkas baseline supaya
     * utangnya tidak bisa diam-diam kembali.
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
}
