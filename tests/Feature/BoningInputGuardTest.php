<?php

namespace Tests\Feature;

use App\Models\Boning;
use App\Models\User;
use App\Support\DocumentNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjagaan isian di modul Boning.
 *
 * Dua di antaranya menyentuh barcode 26 karakter, yang artinya kesalahan di
 * sini tidak berhenti di satu dokumen -- ia ikut tercetak di label dan
 * terbawa ke seluruh modul sesudahnya.
 */
class BoningInputGuardTest extends TestCase
{
    use RefreshDatabase;

    private function pageSource(string $page): string
    {
        return file_get_contents(app_path('Filament/Admin/Resources/BoningResource/Pages/'.$page));
    }

    /**
     * Nomor dokumen tidak lagi dihitung dari jumlah baris.
     *
     * Cara lama membuat nomor bisa TERULANG: satu dokumen yang dihapus
     * permanen membuat hitungan turun, dan dokumen berikutnya memakai nomor
     * yang sudah dipakai -- langsung menabrak unique index dengan error yang
     * tidak menjelaskan apa-apa.
     */
    public function test_the_document_number_survives_a_permanently_deleted_document(): void
    {
        $user = User::create([
            'name' => 'Operator', 'username' => 'operator_boning', 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'programmer', 'is_active' => true,
        ]);
        $this->actingAs($user);

        $first = Boning::create(['boning_date' => now()->toDateString(), 'created_by' => $user->id]);
        $second = Boning::create(['boning_date' => now()->toDateString(), 'created_by' => $user->id]);
        $third = Boning::create(['boning_date' => now()->toDateString(), 'created_by' => $user->id]);

        $this->assertSame('BN'.date('y').'003', $third->doc_no);

        // Yang dihapus permanen adalah dokumen di TENGAH -- inilah kasus yang
        // dulu merusak. Dengan cara lama, hitungan turun menjadi 2 dan dokumen
        // berikutnya mencoba nomor 003 yang MASIH ADA, langsung menabrak
        // unique index. Membaca nomor terakhir tidak punya masalah itu.
        $second->forceDelete();

        $fourth = Boning::create(['boning_date' => now()->toDateString(), 'created_by' => $user->id]);

        $this->assertSame('BN'.date('y').'004', $fourth->doc_no);
        $this->assertNotSame($third->doc_no, $fourth->doc_no);
    }

    /** Nomor yang ditampilkan di form sama dengan yang akan tersimpan. */
    public function test_the_form_preview_uses_the_same_path_as_the_save(): void
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/BoningResource.php'));

        $this->assertStringContainsString('DocumentNumber::next', $source);
        $this->assertStringNotContainsString('$sequence = $count + 1', $source);
    }

    /**
     * pH tidak boleh berupa input bertombol panah.
     *
     * Rentangnya cuma 5,4-5,7 dengan langkah 0,1, jadi satu sentuhan panah
     * menggeser nilainya tanpa terasa -- dan pH ikut masuk ke barcode 26
     * karakter, sehingga digit yang salah berarti barcode yang salah arti.
     */
    public function test_the_ph_field_has_no_spinner_arrows(): void
    {
        $source = $this->pageSource('LabelingBoning.php');
        $field = substr($source, strpos($source, "TextInput::make('ph_level')"), 1400);

        $this->assertStringNotContainsString('->numeric()', $field);
        $this->assertStringNotContainsString('->minValue(', $field);
        $this->assertStringNotContainsString('->maxValue(', $field);
        $this->assertStringContainsString("'min:5.4'", $field);
        $this->assertStringContainsString("'max:5.7'", $field);
    }

    /** Qty pemakaian material wajib lebih dari nol. */
    public function test_material_usage_quantity_must_be_positive(): void
    {
        $source = $this->pageSource('MaterialUsageBoning.php');
        $field = substr($source, strpos($source, "TextInput::make('qty')"), 900);

        $this->assertStringNotContainsString('->numeric()', $field);
        $this->assertStringContainsString("'gt:0'", $field);
    }

    /** Helper penomoran memang dipakai, bukan sekadar ada. */
    public function test_the_shared_numbering_helper_is_used(): void
    {
        $this->assertStringContainsString(
            'DocumentNumber::next',
            file_get_contents(app_path('Models/Boning.php')),
        );

        $this->assertSame(
            'BN'.date('y').'001',
            DocumentNumber::next(
                query: Boning::withTrashed(),
                column: 'doc_no',
                prefix: 'BN'.date('y'),
                padding: 3,
            ),
        );
    }
}
