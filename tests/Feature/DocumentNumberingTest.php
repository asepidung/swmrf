<?php

namespace Tests\Feature;

use App\Models\Mutation;
use App\Models\User;
use App\Support\DocumentNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penomoran dokumen tidak boleh berhenti bekerja saat digitnya bertambah.
 *
 * Sepuluh generator di aplikasi ini menulis urutannya dengan pola yang sama:
 * `substr($nomor, -3)` lalu `str_pad(..., 3)`. Polanya benar sampai dokumen
 * ke-1000, dan gagal pada dokumen SESUDAHNYA -- potongan tiga karakter
 * terakhir dari `1000` adalah `000`, urutan berikutnya dihitung 1, dan nomor
 * yang sudah dipakai sepuluh bulan sebelumnya dicoba lagi. Unique index
 * menolaknya dengan error yang tidak menjelaskan apa-apa.
 *
 * Seberapa cepat batas itu tercapai berbeda per modul dan jangan ditebak dari
 * nama dokumennya -- satu nomor Carcass menampung banyak sapi sekaligus,
 * bukan satu nomor per ekor. Yang menentukan bukan lajunya, melainkan bahwa
 * kegagalannya datang mendadak tanpa gejala apa pun sebelumnya.
 *
 * Yang dijaga di sini POLANYA, bukan sepuluh berkas yang kebetulan sudah
 * ketahuan -- generator berikutnya akan ditulis dengan menyalin tetangganya.
 */
class DocumentNumberingTest extends TestCase
{
    use RefreshDatabase;

    private function operator(string $username): User
    {
        $user = User::create([
            'name' => 'Operator',
            'username' => $username,
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'programmer',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        return $user;
    }

    private function makeMutation(User $user, ?string $number = null): Mutation
    {
        $warehouse = \App\Models\Warehouse::firstOrCreate(
            ['code' => 'JONGGOL'],
            ['name' => 'JONGGOL', 'is_active' => true],
        );

        $destination = \App\Models\Warehouse::firstOrCreate(
            ['code' => 'PERUM'],
            ['name' => 'PERUM', 'is_active' => true],
        );

        return Mutation::create([
            'mutation_number' => $number,
            'mutation_date' => now()->toDateString(),
            'from_warehouse_id' => $warehouse->id,
            'to_warehouse_id' => $destination->id,
            'created_by' => $user->id,
        ]);
    }

    /** Nomor pertama memakai padding yang diminta, jadi format lama tidak berubah. */
    public function test_the_first_number_keeps_the_requested_padding(): void
    {
        $this->assertSame('MT#26001', DocumentNumber::next(
            query: Mutation::query(),
            column: 'mutation_number',
            prefix: 'MT#26',
            padding: 3,
        ));
    }

    /**
     * Urutan dibaca UTUH, bukan dari karakter terakhirnya.
     *
     * Inilah bedanya: `substr('MT#261000', -3)` menghasilkan '000', sementara
     * membaca seluruh bagian setelah prefix menghasilkan 1000.
     */
    public function test_the_sequence_is_read_whole_not_by_its_last_characters(): void
    {
        $this->assertSame(1000, DocumentNumber::sequenceOf('MT#261000', 'MT#26'));
        $this->assertSame(999, DocumentNumber::sequenceOf('MT#26999', 'MT#26'));
        $this->assertSame(12345, DocumentNumber::sequenceOf('SWM-INV#2612345', 'SWM-INV#26'));

        // Pola lama, sebagai pembanding: inilah angka yang dulu terbaca.
        $this->assertSame(0, (int) substr('MT#261000', -3));
    }

    /** Melewati 999, nomornya tumbuh sendiri dan tidak pernah mundur. */
    public function test_numbering_grows_past_its_padding(): void
    {
        $user = $this->operator('operator_numbering');

        $this->makeMutation($user, 'MT#'.date('y').'999');

        $this->assertSame('MT#'.date('y').'1000', $this->makeMutation($user)->mutation_number);
        $this->assertSame('MT#'.date('y').'1001', $this->makeMutation($user)->mutation_number);
    }

    /**
     * Nomor terakhir dicari berdasarkan PANJANG lebih dulu.
     *
     * `orderBy` biasa membandingkan sebagai teks: `...999` dianggap lebih
     * besar daripada `...1000` karena '9' > '1'. Tanpa urutan panjang, nomor
     * berikutnya akan mundur ke angka yang sudah terpakai.
     */
    public function test_the_latest_number_is_found_by_length_first(): void
    {
        $user = $this->operator('operator_length');

        foreach (['999', '1000'] as $sequence) {
            $this->makeMutation($user, 'MT#'.date('y').$sequence);
        }

        $this->assertSame('MT#'.date('y').'1001', DocumentNumber::next(
            query: Mutation::query(),
            column: 'mutation_number',
            prefix: 'MT#'.date('y'),
            padding: 3,
        ));
    }

    /** Tahun berikutnya mulai dari satu lagi. */
    public function test_numbering_resets_when_the_year_prefix_changes(): void
    {
        $user = $this->operator('operator_year');

        $this->makeMutation($user, 'MT#26500');

        $this->assertSame('MT#27001', DocumentNumber::next(
            query: Mutation::query(),
            column: 'mutation_number',
            prefix: 'MT#27',
            padding: 3,
        ));
    }

    /**
     * Penjagaan pola: tidak ada generator yang membaca urutannya dengan
     * memotong karakter terakhir.
     *
     * Dua pola yang ditolak:
     *
     *  - urutan dibaca dengan memotong karakter terakhir (`substr(-N)`), yang
     *    kehilangan angka teratas begitu digitnya bertambah;
     *  - urutan dihitung dari JUMLAH BARIS, yang ditemukan di Boning. Satu
     *    dokumen yang dihapus permanen membuat hitungan turun, dan nomor yang
     *    sudah dipakai dicoba lagi -- menabrak unique index.
     *
     * Counter BARCODE sengaja dikecualikan. Barcode di sini 26 karakter
     * berformat tetap -- counternya memang TIDAK boleh tumbuh, karena
     * panjangnya bagian dari format itu sendiri. Persoalan berbeda.
     */
    public function test_no_document_generator_reads_its_sequence_by_trailing_characters(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path())
        );

        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            // Hanya berkas yang benar-benar menyusun nomor dokumen.
            if (! str_contains($source, 'str_pad')) {
                continue;
            }

            // Counter barcode berformat tetap; lihat penjelasan di atas.
            if (str_contains($source, 'barcode') || str_contains($source, 'Barcode')) {
                continue;
            }

            // Helper-nya sendiri menyebut pola lama di komentar, sebagai
            // penjelasan kenapa ia dibuat.
            if ($file->getFilename() === 'DocumentNumber.php') {
                continue;
            }

            if (preg_match('/substr\([^,)]+,\s*-\d+\s*\)/', $source, $match)) {
                $offenders[] = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getPathname())
                    .'  ('.$match[0].')';

                continue;
            }

            // Pola kedua, ditemukan di Boning: urutan dihitung dari JUMLAH
            // BARIS, bukan dari nomor terakhir. Satu dokumen yang dihapus
            // permanen membuat hitungan turun, dan nomor yang sudah dipakai
            // dicoba lagi.
            if (preg_match('/->count\(\)\s*;?\s*
\s*\$\w*[Ss]equence\s*=\s*\$\w+\s*\+\s*1/', $source, $match)) {
                $offenders[] = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getPathname())
                    .'  (urutan dihitung dari count())';
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            'Generator berikut membaca urutannya dengan memotong karakter terakhir. Polanya benar '
                .'sampai urutannya melewati jumlah digit itu, lalu angka teratasnya hilang dan nomor '
                .'yang sudah terpakai dicoba lagi -- tanpa peringatan apa pun sebelumnya. Pakai '
                ."App\\Support\\DocumentNumber::next():\n".implode("\n", $offenders),
        );
    }

    /**
     * Nomor dokumen tidak disusun dengan tangan.
     *
     * Penjagaan di atas hanya melihat berkas yang memuat `str_pad`. Nomor
     * MR# dan BR# memakai `sprintf('%03s')`, jadi lolos -- dan keduanya
     * memang salah. Yang ditolak di sini tiga bentuknya sekaligus:
     *
     *  - urutan disusun sendiri dengan `str_pad` atas variabel nomor;
     *  - digit terakhir dipungut dari nomor sebelumnya;
     *  - nomor terakhir dicari dengan `orderBy(..., 'desc')` biasa, yang
     *    membandingkan sebagai TEKS: `...999` dianggap lebih besar daripada
     *    `...1000`.
     */
    public function test_no_document_number_is_assembled_by_hand(): void
    {
        $pelanggar = [];

        foreach ($this->berkasPhp() as $berkas) {
            // Helper-nya sendiri memang menyusun nomornya; itu tugasnya.
            if (str_ends_with($this->relatif($berkas), 'Support/DocumentNumber.php')) {
                continue;
            }

            $isi = $this->tanpaKomentar(file_get_contents($berkas));

            foreach ([
                '/str_pad\(\s*\$\w*(?:number|nomor|counter|sequence|urut)/i' => 'menyusun nomor sendiri',
                '/substr\(\s*\$\w+->\w*(?:number|nomor)\w*\s*,\s*-\d/i' => 'memungut digit terakhir nomor',
                "/orderBy\(\s*'\w*(?:number|nomor)\w*'\s*,\s*'desc'\s*\)/i" => 'mengurutkan nomor sebagai teks',
            ] as $pola => $sebab) {
                if (preg_match($pola, $isi)) {
                    $pelanggar[] = $this->relatif($berkas).'  -- '.$sebab;
                }
            }
        }

        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Penomoran berikut disusun sendiri, bukan lewat `App\\Support\\DocumentNumber::next()`. "
            ."Yang hilang bukan cuma kerapian: penguncian barisnya, urutan menurut PANJANG, dan "
            ."padding sebagai batas bawah, ketiganya sekaligus:\n".implode("\n", $pelanggar),
        );
    }

    /**
     * Nomor dokumen tidak diturunkan dari JUMLAH BARIS.
     *
     * Bentuk ini yang paling menipu: ia terlihat benar selama belum pernah
     * ada baris yang benar-benar hilang. Penjagaan lama mencari pola dua
     * baris `->count()` lalu `$sequence = $x + 1` di berkas yang memuat
     * `str_pad`; MR# dan BR# memakai nama variabel lain dan tidak memuat
     * `str_pad`, jadi tidak pernah tersentuh.
     */
    public function test_no_document_number_is_derived_from_a_row_count(): void
    {
        $pelanggar = [];

        foreach ($this->berkasPhp() as $berkas) {
            $isi = $this->tanpaKomentar(file_get_contents($berkas));

            if (preg_match('/\$\w*(?:urut|number|nomor|counter|sequence)\w*\s*=\s*\$\w*count\w*\s*\+\s*1/i', $isi, $m)) {
                $pelanggar[] = $this->relatif($berkas).'  ('.$m[0].')';
            }
        }

        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Nomor dokumen berikut diturunkan dari jumlah baris. Menghitung baris bukan hal yang "
            ."sama dengan mengambil nomor tertinggi -- dan `lockForUpdate()` pada sebuah `count()` "
            ."tidak mengunci apa pun ketika hasilnya nol:\n".implode("\n", $pelanggar),
        );
    }

    /** @return \Generator<string> */
    private function berkasPhp(): \Generator
    {
        $berkas = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path())
        );

        foreach ($berkas as $satu) {
            if ($satu->isFile() && $satu->getExtension() === 'php') {
                yield $satu->getPathname();
            }
        }
    }

    /** Komentar dibuang supaya keterangannya tidak ikut tertuduh. */
    private function tanpaKomentar(string $isi): string
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

    private function relatif(string $jalur): string
    {
        $jalur = str_replace(chr(92), '/', $jalur);

        return str_replace(str_replace(chr(92), '/', base_path()).'/', '', $jalur);
    }
}
