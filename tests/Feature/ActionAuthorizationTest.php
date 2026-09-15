<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\DeliveryOrderResource;
use App\Filament\Admin\Resources\MaterialRequisitionResource;
use App\Filament\Admin\Resources\MaterialStockTakeResource;
use App\Filament\Admin\Resources\ProductRequisitionResource;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\DeliveryOrder;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialRequisition;
use App\Models\MaterialStockTake;
use App\Models\MaterialUnit;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRequisition;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\Tally;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tombol yang mengubah keadaan harus memeriksa SIAPA yang menekannya.
 *
 * Pemindaian seluruh `app/` menemukan enam belas aksi yang mengubah keadaan
 * tanpa satu pun pemeriksaan izin -- hanya status dokumennya yang diperiksa.
 *
 * Yang paling tajam bukan tombolnya, melainkan HALAMANNYA: empat halaman
 * persetujuan permintaan dan satu halaman persetujuan surat jalan tidak punya
 * `canAccess()` sama sekali. Izinnya ada, sudah di-seed, muncul di form Hak
 * Akses, dan diperiksa ketika memutuskan apakah TAUTANNYA ditampilkan -- lalu
 * halamannya sendiri terbuka bagi siapa pun yang tahu alamatnya.
 *
 * Sudah dibuktikan sebelum diperbaiki: pengguna dengan hanya
 * `view_product_requisitions` mengembalikan `true` untuk halaman
 * "Approve & Generate PO".
 */
class ActionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Halaman yang mengubah keadaan, beserta izin yang menjaganya.
     *
     * @return array<int, array{0: class-string, 1: string}>
     */
    public static function halamanBerbahaya(): array
    {
        return [
            [\App\Filament\Admin\Resources\ProductRequisitionResource\Pages\ApproveFinanceProductRequisition::class, 'approve_product_requisitions'],
            [\App\Filament\Admin\Resources\ProductRequisitionResource\Pages\ReviewProductRequisition::class, 'review_product_requisitions'],
            [\App\Filament\Admin\Resources\MaterialRequisitionResource\Pages\ApproveFinanceMaterialRequisition::class, 'approve_material_requisitions'],
            [\App\Filament\Admin\Resources\MaterialRequisitionResource\Pages\ReviewMaterialRequisition::class, 'review_material_requisitions'],
            [\App\Filament\Admin\Resources\DeliveryOrderResource\Pages\ApproveDeliveryOrder::class, 'approve_delivery_orders'],
            [\App\Filament\Admin\Resources\MaterialStockTakeResource\Pages\ManageMaterialStockTakeItems::class, 'view_material_stock_takes'],
        ];
    }

    /**
     * @dataProvider halamanBerbahaya
     */
    public function test_a_state_changing_page_is_closed_without_its_permission(string $halaman, string $izin): void
    {
        $orangLuar = User::create([
            'name' => 'Luar', 'username' => 'luar_'.uniqid(),
            'password' => 'secret-password', 'gender' => 'L',
            'role' => 'employee', 'is_active' => true,
        ]);

        $this->actingAs($orangLuar->fresh());

        $this->assertFalse(
            $halaman::canAccess(['record' => 1]),
            "{$halaman} terbuka tanpa izin {$izin}.",
        );

        $orangLuar->permissions()->attach(
            Permission::firstOrCreate(
                ['name' => $izin],
                ['module_name' => 'Test', 'description' => $izin],
            )->id
        );

        $this->actingAs($orangLuar->fresh());

        $this->assertTrue(
            $halaman::canAccess(['record' => 1]),
            "{$halaman} tetap tertutup padahal izin {$izin} sudah diberikan.",
        );
    }

    /**
     * Penjaga di atas cuma memanggil `Halaman::canAccess()` secara statis --
     * itu MEMBUKTIKAN logikanya benar, tapi TIDAK membuktikan Filament
     * sungguh MEMANGGIL logika itu saat halamannya diminta lewat alamat
     * sungguhan. 15 September 2026: sempat disangka `canAccess()` pada
     * Resource Page adalah kode mati (cuma dipakai membangun tautan
     * sub-navigasi), berdasarkan bacaan kode `CanAuthorizeResourceAccess`
     * saja -- dan itu KELIRU. Dibuktikan lewat test HTTP sungguhan:
     * `Filament\Pages\Page` (induk `Resources\Pages\Page`) JUGA memakai
     * trait `CanAuthorizeAccess`, dan Livewire memanggil SEMUA hook
     * `mount<NamaTrait>` dari SEMUA trait di rantai kelas saat komponen
     * full-page sungguh dimuat -- bukan cuma satu. `canAccess()` milik
     * Page memang tergerbangi, hanya saja `Livewire::test()->mount()` tidak
     * melalui jalur yang sama seperti request HTTP asli.
     *
     * Test ini menutup jarak itu: memanggil rute sungguhan (`$this->get(...)`)
     * untuk setiap halaman di `halamanBerbahaya()`, supaya kalau suatu saat
     * versi Filament berubah dan hook trait ini berhenti dipanggil ganda,
     * penjaga ini yang pertama menggigit -- bukan ditemukan lewat insiden.
     */
    public function test_a_state_changing_page_is_closed_over_http_without_its_permission(): void
    {
        $orangLuar = User::create([
            'name' => 'Luar HTTP', 'username' => 'luar_http_'.uniqid(),
            'password' => 'secret-password', 'gender' => 'L',
            'role' => 'employee', 'is_active' => true,
        ]);

        foreach ($this->halamanBerbahayaUrls() as [$url, $izin, $izinLain]) {
            $this->actingAs($orangLuar->fresh());

            $this->get($url)->assertForbidden();

            // Izin aksinya sendiri, DITAMBAH izin lain yang ternyata juga
            // menggerbangi halaman yang sama (lihat catatan panjang di
            // `halamanBerbahayaUrls()`). Tanpa semuanya, halaman tetap 403
            // walau izin aksinya sudah ada -- itu bukan bug, tapi kalau
            // tidak diberikan di sini test akan salah menuduh gerbang
            // halamannya yang rusak.
            foreach ([$izin, ...$izinLain] as $nama) {
                $orangLuar->permissions()->attach(
                    Permission::firstOrCreate(
                        ['name' => $nama],
                        ['module_name' => 'Test', 'description' => $nama],
                    )->id
                );
            }

            $this->actingAs($orangLuar->fresh());

            $this->get($url)->assertSuccessful();

            $orangLuar->permissions()->detach();
        }
    }

    /**
     * Satu record sungguhan per halaman berbahaya, supaya route model
     * binding menemukan sesuatu (404 duluan akan menyamarkan 403 yang
     * seharusnya diuji).
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function halamanBerbahayaUrls(): array
    {
        $orang = User::create([
            'name' => 'Pembuat Fixture', 'username' => 'fixture_'.uniqid(),
            'password' => 'secret-password', 'gender' => 'L',
            'role' => 'programmer', 'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'name' => 'FIXTURE SUPPLIER', 'address' => 'X', 'pic' => 'X', 'top_days' => 30,
        ]);

        $productCategory = ProductCategory::create(['name' => 'FIXTURE PRODUCT CAT', 'prefix' => 'FX']);
        $product = Product::create([
            'name' => 'FIXTURE PRODUCT', 'code' => 'FX001', 'category_id' => $productCategory->id,
            'structure_type' => 'main', 'is_active' => true,
        ]);

        // Kedua halaman keputusan Product Requisition masing-masing hanya
        // terbuka pada TAHAPNYA sendiri (`ApproveFinanceProductRequisition::
        // APPROVABLE_STATUS`, `ReviewProductRequisition::EDITABLE_STATUSES`)
        // -- di luar itu mount() langsung redirect, jadi butuh dua dokumen
        // dengan status berbeda, bukan satu dipakai berdua.
        $productRequisitionForApproval = ProductRequisition::create([
            'user_id' => $orang->id, 'supplier_id' => $supplier->id,
            'due_date' => now()->toDateString(), 'status' => 'Pending Finance',
        ]);
        $productRequisitionForApproval->items()->create([
            'product_id' => $product->id, 'qty' => 10, 'price' => 1000, 'subtotal' => 10000,
        ]);

        $productRequisitionForReview = ProductRequisition::create([
            'user_id' => $orang->id, 'supplier_id' => $supplier->id,
            'due_date' => now()->toDateString(), 'status' => 'Requested',
        ]);
        $productRequisitionForReview->items()->create([
            'product_id' => $product->id, 'qty' => 10, 'price' => 1000, 'subtotal' => 10000,
        ]);

        $materialCategory = MaterialCategory::create(['name' => 'FIXTURE MATERIAL CAT']);
        $materialUnit = MaterialUnit::create(['name' => 'PCS']);
        $material = Material::create([
            'code' => 'MFX001', 'name' => 'FIXTURE MATERIAL',
            'material_category_id' => $materialCategory->id, 'material_unit_id' => $materialUnit->id,
            'is_active' => true,
        ]);

        $materialRequisition = MaterialRequisition::create([
            'user_id' => $orang->id, 'supplier_id' => $supplier->id,
            'due_date' => now()->toDateString(), 'status' => 'Pending',
        ]);
        $materialRequisition->items()->create([
            'material_id' => $material->id, 'qty' => 10, 'price' => 1000, 'subtotal' => 10000,
        ]);

        $materialStockTake = MaterialStockTake::create([
            'document_number' => 'MSO-FIXTURE-'.uniqid(),
            'period' => now()->format('Y-m'), 'date' => now()->toDateString(),
            'status' => MaterialStockTake::STATUS_IN_PROGRESS, 'created_by' => $orang->id,
        ]);

        $segment = CustomerSegment::create(['name' => 'FIXTURE SEGMENT', 'is_active' => true]);
        $customer = Customer::create([
            'name' => 'FIXTURE CUSTOMER', 'customer_segment_id' => $segment->id,
            'address' => 'X', 'pic' => 'X', 'phone' => '08', 'top' => 30,
        ]);
        $salesOrder = SalesOrder::create([
            'customer_id' => $customer->id, 'delivery_date' => now()->addDay()->format('Y-m-d'),
            'po_number' => 'PO-FIXTURE', 'created_by' => $orang->id, 'status' => 'ready',
        ]);
        $tally = Tally::create(['sales_order_id' => $salesOrder->id, 'status' => 'locked']);
        $deliveryOrder = DeliveryOrder::create([
            'tally_id' => $tally->id, 'sales_order_id' => $salesOrder->id, 'customer_id' => $customer->id,
            'delivery_date' => now()->addDay()->format('Y-m-d'), 'po_number' => 'PO-FIXTURE', 'status' => 'Ready',
        ]);

        return [
            // Halaman ApproveFinance/Review adalah `EditRecord` Filament --
            // itu berarti ADA gerbang ketiga di luar canAccess() halaman
            // dan canViewAny() Resource: `EditRecord::mount()` sendiri
            // memanggil `authorizeAccess()` yang mengecek
            // `Resource::canEdit()` (Policy `update`). Untuk ProductRequisition/
            // MaterialRequisition itu berarti izin `edit_*` juga wajib ada
            // -- bukan cuma izin aksi dan izin lihat.
            [ProductRequisitionResource::getUrl('approve-finance', ['record' => $productRequisitionForApproval->getKey()]), 'approve_product_requisitions', ['view_product_requisitions', 'edit_product_requisitions']],
            [ProductRequisitionResource::getUrl('review', ['record' => $productRequisitionForReview->getKey()]), 'review_product_requisitions', ['view_product_requisitions', 'edit_product_requisitions']],
            [MaterialRequisitionResource::getUrl('approve-finance', ['record' => $materialRequisition->getKey()]), 'approve_material_requisitions', ['view_material_requisitions', 'edit_material_requisitions']],
            [MaterialRequisitionResource::getUrl('review', ['record' => $materialRequisition->getKey()]), 'review_material_requisitions', ['view_material_requisitions', 'edit_material_requisitions']],
            [DeliveryOrderResource::getUrl('approve', ['record' => $deliveryOrder->getKey()]), 'approve_delivery_orders', ['view_delivery_orders']],
            [MaterialStockTakeResource::getUrl('items', ['record' => $materialStockTake->getKey()]), 'view_material_stock_takes', []],
        ];
    }

    /**
     * Aksi yang mengubah keadaan tidak boleh hanya memeriksa status dokumen.
     *
     * Pemindainya kasar dengan sengaja: yang dicari cuma apakah di dalam
     * potongan aksinya ada `hasPermission` atau `isProgrammer` sama sekali.
     * Penjaga yang menuntut lebih dari itu akan gampang salah menuduh, dan
     * penjaga yang salah menuduh pada akhirnya dimatikan orang.
     */
    public function test_no_state_changing_action_is_left_unguarded(): void
    {
        $berat = '/Action::make\(\s*\'([a-zA-Z_]*(?:delete|hapus|void|cancel|batal|approve|'
            .'setujui|finish|selesai|complete|lock|kunci|unlock|revert|post|close|'
            .'generate|issue|terbit)[a-zA-Z_]*)\'\s*\)/i';

        $pelanggar = [];

        foreach ($this->berkasPhp() as $berkas) {
            $isi = $this->tanpaKomentar(file_get_contents($berkas));

            // Halaman yang sudah menjaga pintunya sendiri tidak perlu
            // mengulang penjagaan di setiap tombol di dalamnya.
            if (str_contains($isi, 'function canAccess')) {
                continue;
            }

            preg_match_all($berat, $isi, $cocok, PREG_OFFSET_CAPTURE);

            foreach ($cocok[1] as $satu) {
                [$nama, $posisi] = $satu;

                $potong = substr($isi, $posisi, 2500);

                // Berhenti di `Action::make` BERIKUTNYA -- apa pun namanya.
                //
                // Semula hanya berhenti di aksi yang namanya ikut daftar
                // berbahaya, sehingga potongan sebuah tombol tautan menyerap
                // `->action(` milik tombol tetangganya dan ikut tertuduh.
                if (preg_match('/Action::make\(/', substr($potong, 20), $berikut, PREG_OFFSET_CAPTURE)) {
                    $potong = substr($potong, 0, $berikut[0][1] + 20);
                }

                if (str_contains($potong, 'hasPermission') || str_contains($potong, 'isProgrammer')) {
                    continue;
                }

                // Aksi yang hanya berpindah halaman tidak mengubah apa pun.
                if (! str_contains($potong, '->action(')) {
                    continue;
                }

                $kunci = $this->relatif($berkas).':'.$nama;

                if (in_array($kunci, $this->sengajaTanpaIzin(), true)) {
                    continue;
                }

                $pelanggar[] = $kunci;
            }
        }

        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Aksi berikut mengubah keadaan tetapi tidak memeriksa siapa yang menekannya:\n"
            .implode("\n", $pelanggar),
        );
    }

    /**
     * Susulan 15 September 2026, atas temuan Tally #1/#3, Mutation #5,
     * Boning #1: bentuk berbeda dari penjaga di atas, dan lolos dari
     * regexnya.
     *
     * Penjaga di atas hanya menuduh `Action::make('nama-berbahaya')` --
     * tapi `Tables\Actions\DeleteAction::make()` (TANPA argumen, nama
     * bawaannya diam-diam `'delete'`) tidak pernah cocok dengan pola itu.
     *
     * Dibuktikan langsung dari source Filament (`vendor/filament/tables/
     * src/Actions/DeleteAction.php::setUp()`): kelas AKSI TABEL ini --
     * beda dari `Filament\Actions\DeleteAction` yang dipakai di
     * `getHeaderActions()` halaman Resource baku, yang OTOMATIS dipasangi
     * `->authorize()` lewat `EditRecord::configureAction()` dkk -- TIDAK
     * PERNAH memasang otorisasi apa pun sendiri, di halaman MANAPUN ia
     * dipakai. Satu-satunya `->hidden()` bawaannya cuma memeriksa
     * `trashed()`. Jadi bedanya BUKAN "halaman custom vs Resource baku"
     * seperti dugaan awal, melainkan NAMESPACE aksinya: `Tables\Actions\`
     * (baris tabel, tidak pernah tergerbangi otomatis) lawan `Actions\`
     * polos di `getHeaderActions()` (tergerbangi otomatis).
     */
    public function test_no_bare_crud_action_is_left_unguarded_on_a_custom_page(): void
    {
        $pelanggar = [];

        foreach ($this->berkasPhp() as $berkas) {
            $isi = $this->tanpaKomentar(file_get_contents($berkas));

            // Halaman yang gerbangnya sendiri sudah menuntut izin yang
            // tepat (canAccess()) tidak perlu mengulang pemeriksaan yang
            // sama di setiap aksi tabel di dalamnya -- siapa pun yang bisa
            // sampai ke tabelnya sudah pasti berizin.
            if (str_contains($isi, 'function canAccess')) {
                continue;
            }

            if (! preg_match('/\bTables\\\\Actions\\\\(?:DeleteAction|ForceDeleteAction|RestoreAction)::make\(\s*\)/', $isi)) {
                continue;
            }

            preg_match_all('/\bTables\\\\Actions\\\\(DeleteAction|ForceDeleteAction|RestoreAction)::make\(\s*\)/', $isi, $cocok, PREG_OFFSET_CAPTURE);

            foreach ($cocok[0] as $index => $satu) {
                [$teks, $posisi] = $satu;
                $nama = $cocok[1][$index][0];

                $potong = substr($isi, $posisi, 1500);

                if (str_contains($potong, 'hasPermission') || str_contains($potong, 'isProgrammer') || str_contains($potong, '->authorize(')) {
                    continue;
                }

                $kunci = $this->relatif($berkas).':'.$nama;

                if (in_array($kunci, [...$this->belumDiperbaikiDiCabangLain(), ...$this->diluarCakupanBatchIni()], true)) {
                    continue;
                }

                $pelanggar[] = $kunci;
            }
        }

        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Aksi CRUD bawaan Filament (Delete/ForceDelete/Restore) dipakai TANPA nama (jadi tanpa izin) "
            ."di halaman yang BUKAN EditRecord/ViewRecord/ListRecords/ManageRelatedRecords -- di luar keempat "
            ."itu Filament tidak memasang otorisasi apa pun secara otomatis:\n".implode("\n", $pelanggar),
        );
    }

    /**
     * Susulan yang sama dengan penjaga di atas: method Livewire BIASA (bukan
     * Filament Action sama sekali) di halaman custom yang menulis/menghapus
     * BeefStock secara langsung -- seperti `ScanTally::scan()` sebelum
     * diperbaiki. Method semacam ini tidak punya `->hidden()`/`->authorize()`
     * yang bisa dipasang; pemeriksaan izinnya WAJIB ditulis tangan di baris
     * pertama badan methodnya sendiri.
     */
    public function test_no_public_method_on_a_custom_page_moves_beef_stock_without_a_permission_check(): void
    {
        $abaikanNama = [
            'mount', 'boot', 'booted', 'render', 'table', 'form', 'infolist',
            'getHeaderActions', 'getFormActions', 'getTableQuery', 'getTitle',
            'getSubheading', 'getHeading', 'getMaxContentWidth', 'getBreadcrumbs',
            'getViewData', 'getSummaryData', 'getProductionSummary', 'getRedirectUrl',
            'mutateFormDataBeforeFill', 'mutateFormDataBeforeSave', 'mutateFormDataBeforeCreate',
            'canAccess', 'shouldRegisterNavigation', 'isPastPodLimit', 'updatedPodLimit',
            'getListeners', 'dehydrateState', 'hydrateState',
        ];

        $pelanggar = [];

        foreach ($this->berkasPhp() as $berkas) {
            $jalur = str_replace('\\', '/', $berkas);

            if (! str_contains($jalur, '/app/Filament/')) {
                continue;
            }

            $isiAsli = file_get_contents($berkas);

            if (! preg_match('/class\s+\w+\s+extends\s+Page\b/', $isiAsli)) {
                continue;
            }

            $isi = $this->tanpaKomentar($isiAsli);

            // Halaman yang sudah menjaga pintunya sendiri (canAccess())
            // tidak perlu mengulang penjagaan di setiap method di
            // dalamnya -- sama seperti penjaga lama di atas.
            if (str_contains($isi, 'function canAccess')) {
                continue;
            }

            preg_match_all('/public function (\w+)\s*\(/', $isi, $cocok, PREG_OFFSET_CAPTURE);

            foreach ($cocok[1] as $satu) {
                [$nama, $posisi] = $satu;

                if (in_array($nama, $abaikanNama, true)) {
                    continue;
                }

                $awalKurung = strpos($isi, '{', $posisi);

                if ($awalKurung === false) {
                    continue;
                }

                $tubuh = $this->tubuhMethod($isi, $awalKurung);

                $menyentuhStok = str_contains($tubuh, 'BeefStock::create(')
                    || (str_contains($tubuh, 'BeefStock::') && str_contains($tubuh, '->delete()'));

                if (! $menyentuhStok) {
                    continue;
                }

                if (str_contains($tubuh, 'hasPermission') || str_contains($tubuh, 'isProgrammer')) {
                    continue;
                }

                $kunci = $this->relatif($berkas).':'.$nama;

                if (in_array($kunci, [...$this->belumDiperbaikiDiCabangLain(), ...$this->diluarCakupanBatchIni()], true)) {
                    continue;
                }

                $pelanggar[] = $kunci;
            }
        }

        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Method Livewire publik berikut, di halaman Filament custom (`extends Page`), menulis/menghapus "
            ."BeefStock tanpa satu pun pemeriksaan izin di badannya sendiri:\n".implode("\n", $pelanggar),
        );
    }

    /** Isi method, dihitung lewat kurawal berpasangan mulai dari `{` pembukanya. */
    private function tubuhMethod(string $isi, int $awalKurung): string
    {
        $dalam = 0;
        $panjang = strlen($isi);

        for ($i = $awalKurung; $i < $panjang; $i++) {
            if ($isi[$i] === '{') {
                $dalam++;
            } elseif ($isi[$i] === '}') {
                $dalam--;

                if ($dalam === 0) {
                    return substr($isi, $awalKurung, $i - $awalKurung + 1);
                }
            }
        }

        return substr($isi, $awalKurung);
    }

    /**
     * Susulan 15 September 2026 dikerjakan sebagai 5 PR TERPISAH (satu per
     * modul), masing-masing bercabang dari `main` yang SAMA -- jadi cabang
     * Tally ini belum melihat perbaikan Boning/Mutation/Repack/Sales Order,
     * dan sebaliknya. Daftar ini murni supaya penjaga BARU di atas bisa
     * hijau di SETIAP cabang sambil menunggu PR sebelahnya digabung --
     * BUKAN keputusan permanen seperti `sengajaTanpaIzin()`. Begitu kelima
     * PR sudah digabung ke `main`, baris-baris ini sudah tidak perlu dan
     * boleh dibuang (penjaganya sendiri yang akan membuktikan sudah aman).
     *
     * @return array<int, string>
     */
    private function belumDiperbaikiDiCabangLain(): array
    {
        return [
            // Tally #1/#3 (issue #419) -- belum digabung ke main saat
            // cabang Sales Order ini dibuat.
            'app/Filament/Admin/Resources/TallyResource/Pages/ScanTally.php:scan',
            'app/Filament/Admin/Resources/TallyResource/Pages/ScanTally.php:DeleteAction',

            // Mutation #5 (issue #421) -- ScanMutation unscan. Ditemukan
            // sekaligus lewat penjaga ini: addBarcode() (aksi SCAN-nya
            // sendiri, sekelas persis dengan Tally::scan() sebelum
            // diperbaiki) juga tanpa satu pun pemeriksaan izin -- ikut
            // masuk PR yang sama.
            'app/Filament/Admin/Resources/MutationResource/Pages/ScanMutation.php:DeleteAction',
            'app/Filament/Admin/Resources/MutationResource/Pages/ScanMutation.php:addBarcode',

            // Boning #1/#2/#3 (issue #420) -- LabelingBoning izin halaman,
            // create() tulis stok tanpa cek, dan hapus/void item baris tabel.
            'app/Filament/Admin/Resources/BoningResource/Pages/LabelingBoning.php:create',
            'app/Filament/Admin/Resources/BoningResource/Pages/LabelingBoning.php:DeleteAction',
            'app/Filament/Admin/Resources/BoningResource.php:DeleteAction',

            // Repack #3/#4 (issue #422) -- kunci barcode + try/catch void
            // bahan/hasil, ditemukan sekaligus jadi digabung satu PR.
            'app/Filament/Admin/Resources/RepackResource/Pages/InputBahanRepack.php:DeleteAction',
            'app/Filament/Admin/Resources/RepackResource/Pages/InputHasilRepack.php:DeleteAction',
            'app/Filament/Admin/Resources/RepackResource/Pages/InputBahanRepack.php:submitBarcode',
            'app/Filament/Admin/Resources/RepackResource/Pages/InputHasilRepack.php:create',
        ];
    }

    /**
     * Susulan 15 September 2026: ditemukan SEKALIGUS oleh penjaga baru di
     * atas, tapi di LUAR lima modul batch ini (Sales Order, Tally, Repack,
     * Mutation, Boning) -- termasuk di modul yang sudah pernah "disisir"
     * sebelumnya (Material Stock, Stock Take). Sudah dilaporkan terpisah ke
     * Hafizh/Owner untuk ditriase sebagai pekerjaan sendiri; SENGAJA belum
     * disentuh di sini supaya tidak memperluas cakupan PR tanpa persetujuan.
     * Jangan dibuang sampai ada keputusan eksplisit menutupnya.
     *
     * `SalesReturnResource` masuk daftar ini juga, tapi alasannya beda:
     * Sales Return memang dikecualikan permanen (lihat `tertunda.md`),
     * bukan menunggu triase.
     *
     * @return array<int, string>
     */
    private function diluarCakupanBatchIni(): array
    {
        return [
            'app/Filament/Admin/Resources/GoodsReceiptMaterialResource.php:DeleteAction',
            'app/Filament/Admin/Resources/GoodsReceiptProductResource/Pages/LabelingGoodsReceiptProduct.php:DeleteAction',
            'app/Filament/Admin/Resources/GoodsReceiptProductResource/Pages/ScanGoodsReceiptProduct.php:DeleteAction',
            'app/Filament/Admin/Resources/MaterialStockTakeResource.php:DeleteAction',
            'app/Filament/Admin/Resources/StockTakeResource/Pages/ScanStockTake.php:DeleteAction',
            'app/Filament/Clusters/MaterialsStock/Resources/MaterialFindingResource.php:DeleteAction',
            'app/Filament/Admin/Resources/SalesReturnResource/Pages/InputReturnItems.php:DeleteAction',
            'app/Filament/Admin/Resources/GoodsReceiptProductResource/Pages/LabelingGoodsReceiptProduct.php:create',
            'app/Filament/Admin/Resources/GoodsReceiptProductResource/Pages/ScanGoodsReceiptProduct.php:scan',
        ];
    }

    /**
     * Aksi yang SENGAJA tidak diberi izin tersendiri.
     *
     * @return array<int, string>
     */
    private function sengajaTanpaIzin(): array
    {
        return [
            // Keputusan Owner, 5 September 2026: "mutasi biarin dulu begitu
            // bro". Kirim dan terima mutasi dipakai harian dan dibiarkan
            // menumpang akses halamannya.
            'app/Filament/Admin/Resources/MutationResource/Pages/ScanMutation.php:finish',
            'app/Filament/Admin/Resources/MutationResource/Pages/ReceiveMutation.php:finish',
            'app/Filament/Admin/Resources/MutationResource/Pages/ReceiveMutation.php:cancel_receive',

            // Membatalkan satu pindaian di dalam opname yang sedang berjalan.
            // Halamannya sendiri hanya terbuka untuk opname yang masih boleh
            // dihitung, dan membatalkan pindaian tidak menyentuh stok.
            'app/Filament/Admin/Resources/StockTakeResource/Pages/ScanStockTake.php:cancel_scan',

            // Membatalkan Sales Order dari layar draft tally. Owner sudah
            // membahas alur ini: "so cancel ya delete aja so nya, cuma kemarin
            // canceled yang cancel orang yang bikin tally".
            'app/Filament/Admin/Resources/TallyResource/Pages/DraftTally.php:cancel',
        ];
    }

    /** @return \Generator<string> */
    private function berkasPhp(): \Generator
    {
        $berkas = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('app'))
        );

        foreach ($berkas as $satu) {
            if ($satu->isFile() && $satu->getExtension() === 'php') {
                yield $satu->getPathname();
            }
        }
    }

    /** Komentar diganti baris kosong supaya nomor barisnya tetap benar. */
    private function tanpaKomentar(string $isi): string
    {
        $hasil = '';

        foreach (@token_get_all($isi) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $hasil .= str_repeat("\n", substr_count($token[1], "\n"));

                continue;
            }

            $hasil .= is_array($token) ? $token[1] : $token;
        }

        return $hasil;
    }

    private function relatif(string $jalur): string
    {
        $jalur = str_replace('\\', '/', $jalur);
        $akar = str_replace('\\', '/', base_path()).'/';

        return str_replace($akar, '', $jalur);
    }
}
