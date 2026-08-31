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
 * Untuk Carcass batas itu bukan teori: satu karkas per sapi yang dipotong,
 * jadi memotong tiga ekor sehari sudah melewati 1.000 dalam setahun.
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
}
