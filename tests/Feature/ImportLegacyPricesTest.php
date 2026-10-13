<?php

namespace Tests\Feature;

use App\Models\CustomerGroup;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * Issue #532: `legacy:import-prices`. Berkas xlsx ASLI berisi harga dan di-gitignore;
 * test hanya memakai berkas kecil karangan sendiri yang meniru bentuknya (sheet LION,
 * HPM, UMUM; kolom produk, harga terbaru, tanggal, customer).
 */
class ImportLegacyPricesTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 1, 'is_active' => true]);
        $this->file = tempnam(sys_get_temp_dir(), 'prices').'.xlsx';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);

        parent::tearDown();
    }

    private function product(string $name): Product
    {
        return Product::create(['name' => $name, 'code' => 'P'.random_int(10000, 99999), 'category_id' => $this->category->id, 'structure_type' => 'main', 'is_active' => true]);
    }

    /**
     * @param  array<string, array<int, array{0: string, 1: int|float, 2?: string, 3?: string}>>  $sheets
     */
    private function workbook(array $sheets): void
    {
        $writer = new Writer();
        $writer->openToFile($this->file);
        $first = true;

        foreach ($sheets as $name => $rows) {
            $first ? $writer->getCurrentSheet()->setName($name) : $writer->addNewSheetAndMakeItCurrent()->setName($name);
            $first = false;

            $writer->addRow(Row::fromValues(['Produk', 'Harga terbaru', 'Tanggal', 'Customer', 'Min', 'Max', 'Jumlah baris', 'Variasi harga']));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues([$row[0], $row[1], $row[2] ?? '2026-10-12', $row[3] ?? 'PELANGGAN UJI', 0, 0, 1, 1]));
            }
        }

        $writer->close();
    }

    private function standardWorkbook(): void
    {
        $this->workbook([
            'LION' => [['BRISKET', 147500], ['SILVERSIDE', 134000, '2025-12-18'], ['TENDERLOIN', 217000], ['TIDAK ADA DI MASTER', 99000]],
            'HPM' => [['BRISKET', 160000], ['TENDERLOIN', 230000]],
            'UMUM' => [['BRISKET', 120000], ['  tenderloin ', 180000]],
        ]);
    }

    private function importPrices(array $options = []): \Illuminate\Testing\PendingCommand
    {
        // Grup dibuat dari nol hanya dengan --create-groups; test lain mengisinya sendiri.
        return $this->artisan('legacy:import-prices', ['--source' => $this->file] + $options + ['--create-groups' => true]);
    }

    private function priceOf(string $group, string $product): ?int
    {
        $groupId = CustomerGroup::where('name', $group)->value('id');
        $productId = Product::where('name', $product)->value('id');
        $price = PriceListItem::whereIn('price_list_id', PriceList::where('customer_group_id', $groupId)->pluck('id'))->where('product_id', $productId)->value('price');

        return $price === null ? null : (int) $price;
    }

    // ---------------------------------------------------------------------

    /** @test */
    public function the_dry_run_reports_and_writes_nothing(): void
    {
        $this->product('BRISKET');
        $this->standardWorkbook();

        $this->importPrices()->expectsOutputToContain('Dry-run')->assertExitCode(0);

        $this->assertSame(0, CustomerGroup::count());
        $this->assertSame(0, PriceList::count());
        $this->assertSame(0, PriceListItem::count());
    }

    /** @test */
    public function apply_creates_the_three_groups_with_their_terms_and_flags(): void
    {
        $this->product('BRISKET');
        $this->standardWorkbook();

        $this->importPrices(['--apply' => true])->assertExitCode(0);

        $lion = CustomerGroup::where('name', 'LION')->first();
        $hypermart = CustomerGroup::where('name', 'HYPERMART')->first();
        $umum = CustomerGroup::where('name', 'UMUM')->first();

        $this->assertSame(6.0, $lion->trading_terms_percent);
        $this->assertTrue($lion->is_costing_reference);
        $this->assertFalse($lion->is_general_price_reference);

        $this->assertSame(16.45, $hypermart->trading_terms_percent);
        $this->assertTrue($hypermart->is_costing_reference);

        $this->assertSame(0.0, $umum->trading_terms_percent);
        $this->assertFalse($umum->is_costing_reference);
        $this->assertTrue($umum->is_general_price_reference);
        $this->assertSame($umum->id, CustomerGroup::generalPriceReference()->id);
    }

    /** @test */
    public function apply_fills_one_price_list_per_group_with_the_latest_price_per_matched_product(): void
    {
        $this->product('BRISKET');
        $this->product('TENDERLOIN');
        $this->standardWorkbook();

        $this->importPrices(['--apply' => true])->assertExitCode(0);

        $this->assertSame(3, PriceList::count());
        $this->assertSame(147500, $this->priceOf('LION', 'BRISKET'));
        $this->assertSame(217000, $this->priceOf('LION', 'TENDERLOIN'));
        $this->assertSame(160000, $this->priceOf('HYPERMART', 'BRISKET'));
        $this->assertSame(120000, $this->priceOf('UMUM', 'BRISKET'));
        $this->assertSame(180000, $this->priceOf('UMUM', 'TENDERLOIN'), 'Nama dicocokkan setelah huruf besar dan spasi dirapikan.');
    }

    /** @test */
    public function products_not_found_in_the_master_are_reported_and_never_guessed(): void
    {
        $this->product('BRISKET');
        $this->standardWorkbook();

        $this->importPrices(['--apply' => true])
            ->expectsOutputToContain('TIDAK ADA DI MASTER')
            ->assertExitCode(0);

        $this->assertSame(0, Product::where('name', 'TIDAK ADA DI MASTER')->count(), 'Produk tidak dibuat.');
    }

    // ---------------------------------------------------------------------
    // Harga rata LION
    // ---------------------------------------------------------------------

    /** @test */
    public function the_lion_average_price_replaces_the_sheet_price_for_the_five_reference_products(): void
    {
        foreach (['SILVERSIDE', 'RUMP', 'CHUCK', 'BLADE', 'CHUCK TENDER'] as $name) {
            $this->product($name);
        }
        $this->standardWorkbook();   // SILVERSIDE di berkas 134.000, lainnya tidak ada

        $this->importPrices(['--apply' => true])->assertExitCode(0);

        foreach (['SILVERSIDE', 'RUMP', 'CHUCK', 'BLADE', 'CHUCK TENDER'] as $name) {
            $this->assertSame(147500, $this->priceOf('LION', $name), "{$name} harus memakai harga rata LION.");
        }

        $note = PriceListItem::where('product_id', Product::where('name', 'SILVERSIDE')->value('id'))->value('note');
        $this->assertStringContainsString('Harga rata LION', $note, 'Ditandai di catatan item.');
        $this->assertNull($this->priceOf('HYPERMART', 'RUMP'), 'Hanya berlaku untuk LION.');
    }

    /** @test */
    public function the_replaced_sheet_price_and_the_missing_reference_products_are_reported(): void
    {
        $this->product('SILVERSIDE');
        $this->standardWorkbook();

        $this->importPrices()
            ->expectsOutputToContain('LION SILVERSIDE: berkas Rp 134.000')
            ->expectsOutputToContain('RUMP -- belum ada di master')
            ->assertExitCode(0);
    }

    /** @test */
    public function the_chucktender_spelling_is_accepted_for_chuck_tender(): void
    {
        $this->product('CHUCKTENDER');
        $this->standardWorkbook();

        $this->importPrices(['--apply' => true])->assertExitCode(0);

        $this->assertSame(147500, $this->priceOf('LION', 'CHUCKTENDER'));
    }

    /** @test */
    public function the_average_price_can_be_set_on_the_command_line(): void
    {
        $this->product('RUMP');
        $this->standardWorkbook();

        $this->importPrices(['--apply' => true, '--lion-average' => 150000])->assertExitCode(0);

        $this->assertSame(150000, $this->priceOf('LION', 'RUMP'));
    }

    // ---------------------------------------------------------------------
    // Idempoten dan dijalankan ulang
    // ---------------------------------------------------------------------

    /** @test */
    public function running_it_twice_does_not_duplicate_anything(): void
    {
        $this->product('BRISKET');
        $this->product('RUMP');
        $this->standardWorkbook();

        $this->importPrices(['--apply' => true])->assertExitCode(0);
        $counts = [CustomerGroup::count(), PriceList::count(), PriceListItem::count()];

        $this->importPrices(['--apply' => true])->assertExitCode(0);

        $this->assertSame($counts, [CustomerGroup::count(), PriceList::count(), PriceListItem::count()]);
    }

    /** @test */
    public function running_again_after_the_master_gains_products_adds_only_the_new_ones(): void
    {
        $this->product('BRISKET');
        $this->standardWorkbook();
        $this->importPrices(['--apply' => true])->assertExitCode(0);
        $before = PriceListItem::count();

        $this->product('TENDERLOIN');
        $this->product('RUMP');
        $this->importPrices(['--apply' => true])->assertExitCode(0);

        $this->assertSame(217000, $this->priceOf('LION', 'TENDERLOIN'));
        $this->assertSame(147500, $this->priceOf('LION', 'RUMP'));
        $this->assertGreaterThan($before, PriceListItem::count());
        $this->assertSame(120000, $this->priceOf('UMUM', 'BRISKET'), 'Yang lama tidak berubah.');
    }

    /** @test */
    public function a_price_changed_by_hand_is_reported_and_kept_unless_overwrite_is_given(): void
    {
        $this->product('BRISKET');
        $this->standardWorkbook();
        $this->importPrices(['--apply' => true])->assertExitCode(0);

        PriceListItem::where('product_id', Product::where('name', 'BRISKET')->value('id'))
            ->whereIn('price_list_id', PriceList::where('customer_group_id', CustomerGroup::where('name', 'UMUM')->value('id'))->pluck('id'))
            ->update(['price' => 125000]);

        $this->importPrices(['--apply' => true])->expectsOutputToContain('tidak ditimpa')->assertExitCode(0);
        $this->assertSame(125000, $this->priceOf('UMUM', 'BRISKET'));

        $this->importPrices(['--apply' => true, '--overwrite' => true])->assertExitCode(0);
        $this->assertSame(120000, $this->priceOf('UMUM', 'BRISKET'));
    }

    // ---------------------------------------------------------------------
    // Grup yang sudah ada
    // ---------------------------------------------------------------------

    /** @test */
    public function an_existing_group_is_matched_by_name_and_corrected_instead_of_duplicated(): void
    {
        $existing = CustomerGroup::create(['name' => 'Lion', 'trading_terms_percent' => 2, 'is_costing_reference' => false]);
        $this->product('BRISKET');
        $this->standardWorkbook();

        $this->importPrices(['--apply' => true])->expectsOutputToContain('diperbaiki')->assertExitCode(0);

        $this->assertSame(1, CustomerGroup::where('name', 'LION')->count());
        $existing->refresh();
        $this->assertSame(6.0, $existing->trading_terms_percent);
        $this->assertTrue($existing->is_costing_reference);
        $this->assertSame(147500, $this->priceOf('LION', 'BRISKET'));
    }

    /** @test */
    public function another_group_already_marked_as_the_general_price_is_reported_and_left_alone(): void
    {
        $other = CustomerGroup::create(['name' => 'REGULER', 'is_general_price_reference' => true]);
        $this->product('BRISKET');
        $this->standardWorkbook();

        $this->importPrices(['--apply' => true])->expectsOutputToContain('sudah ditandai harga umum')->assertExitCode(0);

        $this->assertSame($other->id, CustomerGroup::generalPriceReference()->id);
        $this->assertFalse((bool) CustomerGroup::where('name', 'UMUM')->value('is_general_price_reference'));
        $this->assertSame(1, CustomerGroup::where('is_general_price_reference', true)->count());
    }

    // ---------------------------------------------------------------------

    // ---------------------------------------------------------------------
    // Acuan costing per produk (hpp.md 7)
    // ---------------------------------------------------------------------

    private function referenceOf(string $product): ?string
    {
        $id = Product::where('name', $product)->value('costing_customer_group_id');

        return $id ? CustomerGroup::find($id)->name : null;
    }

    /** @test */
    public function reference_products_get_their_group_from_the_mapping_and_others_stay_empty(): void
    {
        foreach (['TOPSIDE', 'TENDERLOIN', 'STRIPLOIN WHOLE', 'CUBEROLL', 'BONE', 'BRISKET'] as $name) {
            $this->product($name);
        }
        $this->standardWorkbook();

        $this->importPrices(['--apply' => true])->assertExitCode(0);

        $this->assertSame('LION', $this->referenceOf('TOPSIDE'));
        $this->assertSame('LION', $this->referenceOf('TENDERLOIN'));
        $this->assertSame('LION', $this->referenceOf('BRISKET'));
        $this->assertSame('HYPERMART', $this->referenceOf('STRIPLOIN WHOLE'));
        $this->assertSame('HYPERMART', $this->referenceOf('CUBEROLL'));
        $this->assertNull($this->referenceOf('BONE'), 'Yang tidak ada di pemetaan dinilai memakai harga umum (kosong).');
    }

    /** @test */
    public function the_source_of_the_reference_is_the_mapping_not_the_sheet(): void
    {
        // CUBEROLL ada di sheet HPM, tetapi TENDERLOIN ada di sheet LION DAN HPM; yang menentukan pemetaan.
        $this->product('TENDERLOIN');
        $this->product('GANDIK');
        $this->workbook([
            'LION' => [['GANDIK', 147500], ['TENDERLOIN', 217000]],
            'HPM' => [['TENDERLOIN', 230000]],
            'UMUM' => [['GANDIK', 100000]],
        ]);

        $this->importPrices(['--apply' => true])->assertExitCode(0);

        $this->assertSame('LION', $this->referenceOf('TENDERLOIN'));
        $this->assertNull($this->referenceOf('GANDIK'), 'Dijual ke LION bukan berarti dinilai memakai harga LION.');
    }

    /** @test */
    public function the_alternative_spellings_are_matched_and_missing_reference_products_are_reported(): void
    {
        $this->product('SHORTRIB');
        $this->product('CHUCKTENDER');
        $this->standardWorkbook();

        $this->importPrices(['--apply' => true])
            ->expectsOutputToContain('Acuan costing LION')
            ->expectsOutputToContain('TOPSIDE -- belum ada di master')
            ->assertExitCode(0);

        $this->assertSame('LION', $this->referenceOf('SHORTRIB'));
        $this->assertSame('LION', $this->referenceOf('CHUCKTENDER'));
    }

    /** @test */
    public function a_reference_filled_by_hand_is_kept_unless_overwrite_is_given(): void
    {
        $manual = CustomerGroup::create(['name' => 'LAIN', 'is_costing_reference' => true]);
        $topside = $this->product('TOPSIDE');
        $topside->update(['costing_customer_group_id' => $manual->id]);
        $this->standardWorkbook();

        $this->importPrices(['--apply' => true])->expectsOutputToContain('acuan sudah LAIN')->assertExitCode(0);
        $this->assertSame('LAIN', $this->referenceOf('TOPSIDE'));

        $this->importPrices(['--apply' => true, '--overwrite' => true])->assertExitCode(0);
        $this->assertSame('LION', $this->referenceOf('TOPSIDE'));
    }

    /** @test */
    public function running_again_after_the_master_gains_reference_products_fills_only_those(): void
    {
        $this->product('TOPSIDE');
        $this->standardWorkbook();
        $this->importPrices(['--apply' => true])->assertExitCode(0);

        $this->product('SILVERSIDE');
        $this->importPrices(['--apply' => true])->assertExitCode(0);

        $this->assertSame('LION', $this->referenceOf('SILVERSIDE'));
        $this->assertSame('LION', $this->referenceOf('TOPSIDE'));
    }

    /** @test */
    public function the_five_percent_rows_are_reported_and_left_empty(): void
    {
        $this->product('BRISKET PEDO');
        $this->standardWorkbook();

        $this->importPrices(['--apply' => true])
            ->expectsOutputToContain('BRISKET PEDO: potongan 5% di Excel, belum ada grupnya')
            ->assertExitCode(0);

        $this->assertNull($this->referenceOf('BRISKET PEDO'));
    }

    /** @test */
    public function the_dry_run_does_not_set_any_reference(): void
    {
        $this->product('TOPSIDE');
        $this->standardWorkbook();

        $this->importPrices()->expectsOutputToContain('acuan costing LION')->assertExitCode(0);

        $this->assertNull($this->referenceOf('TOPSIDE'));
    }

    /** @test */
    public function after_the_import_costing_values_a_lion_product_with_the_lion_price_minus_six_percent(): void
    {
        $topside = $this->product('TOPSIDE');
        $rump = $this->product('RUMP');
        $bone = $this->product('BONE');
        $this->workbook([
            'LION' => [['TOPSIDE', 150000]],
            'HPM' => [['TOPSIDE', 160000]],
            'UMUM' => [['TOPSIDE', 140000], ['BONE', 6000]],
        ]);

        $this->importPrices(['--apply' => true])->assertExitCode(0);

        $lion = \App\Services\ReferencePrice::forProduct($topside->fresh());
        $this->assertSame(150000.0, $lion['gross_price']);
        $this->assertSame(6.0, $lion['trading_terms_percent']);
        $this->assertSame(141000.0, $lion['net_price'], '150.000 dikurangi 6%.');

        $average = \App\Services\ReferencePrice::forProduct($rump->fresh());
        $this->assertSame(147500.0, $average['gross_price'], 'Harga rata LION.');
        $this->assertSame(138650.0, $average['net_price']);

        $general = \App\Services\ReferencePrice::forProduct($bone->fresh());
        $this->assertSame(6000.0, $general['gross_price'], 'Produk tanpa acuan memakai harga umum.');
        $this->assertSame(0.0, $general['trading_terms_percent']);
    }

    // ---------------------------------------------------------------------
    // Grup datang dari master legacy dengan id legacy
    // ---------------------------------------------------------------------

    /** @test */
    public function without_create_groups_it_stops_when_lion_or_hypermart_are_not_in_the_master_yet(): void
    {
        $this->product('BRISKET');
        $this->standardWorkbook();

        $this->artisan('legacy:import-prices', ['--source' => $this->file, '--apply' => true])
            ->expectsOutputToContain('Grup belum ada di master: LION, HYPERMART')
            ->assertExitCode(1);

        $this->assertSame(0, CustomerGroup::count());
        $this->assertSame(0, PriceList::count());
    }

    /** @test */
    public function the_legacy_groups_are_matched_by_name_keeping_their_ids_including_hpm_for_hypermart(): void
    {
        $lion = CustomerGroup::unguarded(fn () => CustomerGroup::create(['id' => 15, 'name' => 'LION']));
        $hpm = CustomerGroup::unguarded(fn () => CustomerGroup::create(['id' => 12, 'name' => 'HPM']));
        $this->product('BRISKET');
        $this->standardWorkbook();

        $this->artisan('legacy:import-prices', ['--source' => $this->file, '--apply' => true])->assertExitCode(0);

        $this->assertSame(15, (int) CustomerGroup::where('name', 'LION')->value('id'), 'Id legacy tidak berubah.');
        $this->assertSame(12, (int) CustomerGroup::where('name', 'HPM')->value('id'));
        $this->assertSame(0, CustomerGroup::where('name', 'HYPERMART')->count(), 'HPM dipakai, HYPERMART tidak dibuat.');
        $this->assertSame(16.45, $hpm->fresh()->trading_terms_percent);
        $this->assertTrue($hpm->fresh()->is_costing_reference);
        $this->assertSame(6.0, $lion->fresh()->trading_terms_percent);
        $this->assertSame(160000, (int) PriceListItem::whereIn('price_list_id', PriceList::where('customer_group_id', 12)->pluck('id'))->value('price'));
        $this->assertSame(1, CustomerGroup::where('is_general_price_reference', true)->count());
    }

    /** @test */
    public function a_missing_file_fails_cleanly(): void
    {
        $this->artisan('legacy:import-prices', ['--source' => '/tidak/ada.xlsx'])
            ->expectsOutputToContain('Berkas tidak ditemukan')
            ->assertExitCode(1);
    }

    /** @test */
    public function a_workbook_without_one_of_the_sheets_fails_before_writing(): void
    {
        $this->workbook(['LION' => [['BRISKET', 1]], 'UMUM' => [['BRISKET', 1]]]);

        $this->importPrices(['--apply' => true])->expectsOutputToContain('Sheet tidak ada')->assertExitCode(1);

        $this->assertSame(0, CustomerGroup::count());
    }

    /** @test */
    public function the_real_file_stays_out_of_the_repository(): void
    {
        $this->assertStringContainsString('/legacy/', file_get_contents(base_path('.gitignore')));
    }
}
