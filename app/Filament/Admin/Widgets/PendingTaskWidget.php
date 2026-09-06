<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\BeefStockAgingResource;
use App\Filament\Admin\Resources\BoningResource;
use App\Filament\Admin\Resources\CarcassResource;
use App\Filament\Admin\Resources\CattleReceivingResource;
use App\Filament\Admin\Resources\CattleWeighingResource;
use App\Filament\Admin\Resources\DeliveryOrderResource;
use App\Filament\Admin\Resources\DeliveryPlanResource;
use App\Filament\Admin\Resources\GoodsReceiptMaterialResource;
use App\Filament\Admin\Resources\GoodsReceiptProductResource;
use App\Filament\Admin\Resources\InvoiceResource;
use App\Filament\Admin\Resources\MaterialRequisitionResource;
use App\Filament\Admin\Resources\MutationResource;
use App\Filament\Admin\Resources\ProductRequisitionResource;
use App\Filament\Admin\Resources\RepackResource;
use App\Filament\Admin\Resources\TallyResource;
use App\Models\BeefStock;
use App\Models\Boning;
use App\Models\CattleReceiving;
use App\Models\CattleWeighing;
use App\Models\DeliveryOrder;
use App\Models\DeliveryPlan;
use App\Models\GoodsReceiptMaterial;
use App\Models\GoodsReceiptProduct;
use App\Models\Invoice;
use App\Models\MaterialRequisition;
use App\Models\MaterialStockTake;
use App\Models\Mutation;
use App\Models\ProductRequisition;
use App\Models\PurchaseCattle;
use App\Models\PurchaseMaterial;
use App\Models\PurchaseProduct;
use App\Models\ProductRequisition as BeefRequisition;
use App\Models\Repack;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\Tally;
use App\Models\StockTake;
use Filament\Widgets\Widget;

/**
 * Daftar pekerjaan tertunda di Dashboard.
 *
 * Sebelumnya setiap baris ditulis tangan di dalam blade: dua puluh blok HTML
 * yang hampir sama, masing-masing dengan kelasnya sendiri, warnanya sendiri,
 * dan kalimatnya sendiri. Tiga akibatnya:
 *
 *  - **Tidak seragam.** Beberapa memakai warna lewat style langsung, yang
 *    lain lewat kelas; jaraknya, ukuran ikonnya, dan tebal hurufnya
 *    berbeda-beda.
 *  - **Tidak bilingual.** Enam belas kalimatnya memakai kunci berbahasa
 *    Indonesia, sehingga tidak pernah berubah saat bahasanya diganti.
 *  - **Sebagian warnanya tidak pernah muncul.** Peringatan stock opname --
 *    notifikasi paling keras di halaman itu, yang memberi tahu bahwa
 *    transaksi sedang terkunci -- memakai kelas merah bawaan Tailwind yang
 *    TIDAK ADA di CSS Filament. Yang bekerja hanya kedipannya, tanpa satu
 *    warna pun. Tidak ada error yang memberitahu.
 *
 * Sekarang seluruh daftarnya berasal dari satu larik, dan blade hanya
 * menggambarnya. Menambah baris baru berarti menambah satu entri, bukan
 * menyalin dua puluh baris HTML.
 */
class PendingTaskWidget extends Widget
{
    protected static string $view = 'filament.admin.widgets.pending-task-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -1;

    /**
     * Peringatan yang menghentikan pekerjaan, bukan sekadar mengingatkan.
     *
     * Selama stock opname berjalan, sebagian transaksi memang terkunci --
     * jadi ini bukan tugas yang bisa dikerjakan, melainkan keadaan yang harus
     * diketahui.
     *
     * @return array<int, array{title: string, body: string}>
     */
    public function alerts(): array
    {
        $alerts = [];

        if (StockTake::whereIn('status', ['DRAFT', 'IN_PROGRESS'])->exists()) {
            $alerts[] = [
                'title' => __('Beef stock take in progress'),
                'body' => __('Some transactions are locked until the stock take is finished.'),
            ];
        }

        if (MaterialStockTake::whereIn('status', ['DRAFT', 'IN_PROGRESS'])->exists()) {
            $alerts[] = [
                'title' => __('Material stock take in progress'),
                'body' => __('Some transactions are locked until the stock take is finished.'),
            ];
        }

        return $alerts;
    }

    /**
     * Pekerjaan tertunda yang boleh dilihat pengguna ini.
     *
     * Tiap hitungan mengembalikan nol bila penggunanya tidak berhak, jadi
     * penyaringan haknya sudah terjadi di dalam metode masing-masing.
     *
     * @return array<int, array{label: string, url: string, tone: string}>
     */
    public function tasks(): array
    {
        $tasks = [];

        foreach ($this->definitions() as [$count, $message, $url, $tone]) {
            if ($count < 1) {
                continue;
            }

            $tasks[] = [
                'label' => __($message, ['count' => $count]),
                'url' => $url,
                'tone' => $tone,
            ];
        }

        return $tasks;
    }

    /**
     * Satu-satunya tempat susunan daftarnya ditulis.
     *
     * Urutannya urutan tampil. Nadanya 'danger' hanya untuk yang berakibat
     * uang tidak tercatat; sisanya 'warning'.
     *
     * @return array<int, array{0: int, 1: string, 2: string, 3: string}>
     */
    protected function definitions(): array
    {
        return [
            // Dua yang pertama paling menentukan: selama Goods Receipt belum
            // dikunci, HUTANGNYA TIDAK TERBENTUK. Barangnya sudah diterima,
            // pemasoknya menunggu, dan sistem tidak mencatat apa pun yang
            // harus dibayar.
            //
            // Kalimatnya tetap sependek baris lain, dan warna merah serta
            // letaknya di paling atas yang membawa beratnya. Versi pertama
            // menjelaskan akibatnya di dalam kalimat -- benar isinya, tetapi
            // panjangnya sendiri yang membuat daftarnya tidak lagi seragam
            // dan justru lebih sulit dibaca sekilas.
            [
                $this->getUnlockedGrProductCount(),
                ':count beef receipts have not been locked yet.',
                \App\Filament\Admin\Resources\GoodsReceiptProductResource::getUrl('index'),
                'danger',
            ],
            [
                $this->getUnlockedGrMaterialCount(),
                ':count material receipts have not been locked yet.',
                \App\Filament\Admin\Resources\GoodsReceiptMaterialResource::getUrl('index'),
                'danger',
            ],

            // Bukti terima yang sudah masuk tetapi belum ditagihkan. Selama
            // belum, uangnya tidak pernah diminta -- barangnya sudah sampai,
            // pelanggannya sudah menandatangani, dan tidak ada tagihan yang
            // berjalan. Bentuk kelalaian yang sama dengan GR yang belum
            // dikunci, hanya di sisi sebaliknya.
            [
                $this->getDraftInvoiceCount(),
                ':count delivery receipts have not been invoiced yet.',
                \App\Filament\Admin\Resources\InvoiceResource::getUrl('draft'),
                'warning',
            ],

            [
                $this->getPendingReceivingCount(),
                ':count cattle purchases have not been received yet.',
                \App\Filament\Admin\Resources\CattleReceivingResource::getUrl('draft'),
                'warning',
            ],
            [
                $this->getPendingWeighingCount(),
                ':count cattle receivings have not been weighed yet.',
                \App\Filament\Admin\Resources\CattleWeighingResource::getUrl('draft'),
                'warning',
            ],
            [
                $this->getPendingCarcassCount(),
                ':count weighings have not been broken down into carcasses yet.',
                \App\Filament\Admin\Resources\CarcassResource::getUrl('draft'),
                'warning',
            ],
            [
                $this->getPendingMaterialRequestCount(),
                ':count material requests are waiting for review.',
                \App\Filament\Admin\Resources\MaterialRequisitionResource::getUrl('index'),
                'warning',
            ],
            [
                $this->getPendingMaterialFinanceCount(),
                ':count material requests are waiting for approval.',
                \App\Filament\Admin\Resources\MaterialRequisitionResource::getUrl('index'),
                'warning',
            ],
            [
                $this->getPendingProductRequestCount(),
                ':count beef requests are waiting for review.',
                \App\Filament\Admin\Resources\ProductRequisitionResource::getUrl('index'),
                'warning',
            ],
            [
                $this->getPendingProductFinanceCount(),
                ':count beef requests are waiting for approval.',
                \App\Filament\Admin\Resources\ProductRequisitionResource::getUrl('index'),
                'warning',
            ],
            [
                $this->getPendingGrMaterialCount(),
                ':count material purchase orders are ready to be received.',
                \App\Filament\Admin\Resources\GoodsReceiptMaterialResource::getUrl('drafts'),
                'warning',
            ],
            [
                $this->getPendingGrProductCount(),
                ':count beef purchase orders are ready to be received.',
                \App\Filament\Admin\Resources\GoodsReceiptProductResource::getUrl('drafts'),
                'warning',
            ],
            [
                $this->getPendingBoningLockCount(),
                ':count bonings have not been locked yet.',
                \App\Filament\Admin\Resources\BoningResource::getUrl('index'),
                'warning',
            ],
            [
                $this->getPendingRepackLockCount(),
                ':count repacks have not been locked yet.',
                \App\Filament\Admin\Resources\RepackResource::getUrl('index'),
                'warning',
            ],
            [
                $this->getPendingTallyCount(),
                ':count sales orders still have no tally.',
                \App\Filament\Admin\Resources\TallyResource::getUrl('draft'),
                'warning',
            ],
            [
                $this->getPendingDeliveryPlanCount(),
                ':count delivery plans for tomorrow have no driver or fleet assigned.',
                \App\Filament\Admin\Resources\DeliveryPlanResource::getUrl('index'),
                'warning',
            ],
            [
                $this->getPendingDeliveryOrderCount(),
                ':count tallies are ready for a delivery order.',
                \App\Filament\Admin\Resources\DeliveryOrderResource::getUrl('draft'),
                'warning',
            ],
            [
                $this->getPendingDeliveryReceiptCount(),
                ':count delivery orders are waiting for their receiving check.',
                \App\Filament\Admin\Resources\DeliveryOrderResource::getUrl('index'),
                'warning',
            ],
            [
                $this->getPendingInvoiceExchangeCount(),
                ':count invoices have not been exchanged yet.',
                \App\Filament\Admin\Resources\InvoiceResource::getUrl('index'),
                'warning',
            ],
            [
                $this->getPendingMutationCount(),
                ':count mutations have not been received yet.',
                \App\Filament\Admin\Resources\MutationResource::getUrl('index'),
                'warning',
            ],
            [
                $this->getAging60DaysCount(),
                ':count items have been in stock for more than 60 days.',
                \App\Filament\Admin\Resources\BeefStockAgingResource::getUrl('index'),
                'warning',
            ],
            /*
             * Tugas QC.
             *
             * Sengaja lewat daftar ini, bukan notifikasi sekali kirim.
             * Notifikasi hilang begitu dibaca atau terlewat; baris di sini
             * BERTAHAN sampai laporannya benar-benar ditulis -- dan itulah
             * bedanya pengingat dengan pekerjaan.
             *
             * Hanya terlihat oleh yang boleh menulis laporannya. Menampilkan
             * tugas kepada orang yang tidak bisa mengerjakannya cuma
             * menambah kebisingan di halaman yang justru dibaca sekilas.
             */
            [
                $this->getDocumentsWithoutQcReportCount(\App\Models\Carcass::class),
                ':count carcass is waiting for its QC report|:count carcasses are waiting for their QC report',
                \App\Filament\Admin\Resources\CarcassResource::getUrl('index'),
                'warning',
            ],
            [
                $this->getDocumentsWithoutQcReportCount(\App\Models\Boning::class),
                ':count boning is waiting for its QC report|:count bonings are waiting for their QC report',
                BoningResource::getUrl('index'),
                'warning',
            ],
            [
                $this->getDocumentsWithoutQcReportCount(\App\Models\GoodsReceiptProduct::class),
                ':count beef receipt is waiting for its QC report|:count beef receipts are waiting for their QC report',
                GoodsReceiptProductResource::getUrl('index'),
                'warning',
            ],
            [
                $this->getDocumentsWithoutQcReportCount(\App\Models\Tally::class),
                ':count tally is waiting for its QC report|:count tallies are waiting for their QC report',
                TallyResource::getUrl('index'),
                'warning',
            ],
            [
                $this->getDocumentsWithoutQcReportCount(\App\Models\Repack::class),
                ':count repack is waiting for its QC report|:count repacks are waiting for their QC report',
                RepackResource::getUrl('index'),
                'warning',
            ],
            [
                $this->getDocumentsWithoutQcReportCount(\App\Models\SalesReturn::class),
                ':count sales return is waiting for its QC report|:count sales returns are waiting for their QC report',
                \App\Filament\Admin\Resources\SalesReturnResource::getUrl('index'),
                'warning',
            ],
            [
                $this->getDocumentsWithoutQcReportCount(\App\Models\StockTake::class),
                ':count stock take is waiting for its QC report|:count stock takes are waiting for their QC report',
                \App\Filament\Admin\Resources\StockTakeResource::getUrl('index'),
                'warning',
            ],
        ];
    }

    /**
     * Dokumen yang belum didampingi laporan QC.
     *
     * Satu jalur untuk semua titik QC -- carcass, boning, GR beef, dan yang
     * menyusul nanti. Menambah titik berarti menambah satu baris di daftar
     * di atas, bukan menyalin metode ini.
     *
     * Yang dihitung LAPORAN yang belum diisi, bukan dokumen yang belum punya
     * laporan. Bedanya penting: laporannya lahir sendiri begitu dokumennya
     * dibuat, jadi dokumen yang ada SEBELUM modul ini memang tidak punya
     * tugas -- dan itu benar. Tidak ada yang pernah diminta menulis
     * laporannya, dan tidak ada yang bisa mengingatnya sekarang.
     *
     * Bentuk sebelumnya menghitung dokumen tanpa laporan dan karena itu
     * butuh batas tanggal supaya seluruh riwayat tidak ikut masuk. Batas itu
     * tidak diperlukan lagi: yang membatasi sekarang keberadaan tugasnya
     * sendiri.
     *
     * @param class-string<\Illuminate\Database\Eloquent\Model> $kelas
     */
    protected function getDocumentsWithoutQcReportCount(string $kelas): int
    {
        if (! (auth()->user()?->hasPermission('create_qc_reports') ?? false)) {
            return 0;
        }

        return \App\Models\QcReport::query()
            ->where('reportable_type', $kelas)
            ->belumDiisi()
            ->count();
    }

    /**
     * Goods Receipt daging yang belum dikunci.
     *
     * Selama belum dikunci, hutang kepada pemasok TIDAK TERBENTUK -- inilah
     * kenapa lupa mengunci berakibat jauh lebih besar daripada sekadar
     * dokumen yang menggantung.
     */
    public function getUnlockedGrProductCount(): int
    {
        if (! $this->may('edit_goods_receipt_products')) {
            return 0;
        }

        return \App\Models\GoodsReceiptProduct::where('is_locked', false)->count();
    }

    /**
     * Bukti terima pengiriman yang belum dibuatkan invoice.
     *
     * Mengikuti daftar Draft Invoice apa adanya, supaya angka di dashboard dan
     * isi halamannya tidak pernah berbeda.
     */
    public function getDraftInvoiceCount(): int
    {
        if (! $this->may('create_invoices')) {
            return 0;
        }

        return \App\Models\DeliveryOrderReceipt::query()
            ->whereDoesntHave('invoice')
            ->count();
    }

    /** Goods Receipt material yang belum dikunci; akibatnya sama. */
    public function getUnlockedGrMaterialCount(): int
    {
        if (! $this->may('edit_gr_materials')) {
            return 0;
        }

        return \App\Models\GoodsReceiptMaterial::where('is_locked', false)->count();
    }

    /** Hak akses, dengan programmer selalu lolos -- mengikuti hasPermission(). */
    protected function may(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->isProgrammer() || $user->hasPermission($permission));
    }

    public function getPendingReceivingCount(): int
    {
        if (!auth()->user()->hasPermission('create_cattle_receivings')) {
            return 0;
        }
        return PurchaseCattle::doesntHave('receivings')->count();
    }

    public function getPendingWeighingCount(): int
    {
        if (!auth()->user()->hasPermission('create_cattle_weighings')) {
            return 0;
        }
        return CattleReceiving::doesntHave('weighing')->count();
    }

    public function getPendingCarcassCount(): int
    {
        if (!auth()->user()->hasPermission('create_carcasses')) {
            return 0;
        }
        return \App\Models\CattleWeighing::whereHas('items', function ($query) {
            $query->whereDoesntHave('carcassItems');
        })->count();
    }

    public function getPendingMaterialRequestCount(): int
    {
        $user = auth()->user();
        if (!$user->isProgrammer() && !$user->hasPermission('review_material_requisitions')) {
            return 0;
        }
        return \App\Models\MaterialRequisition::where('status', 'Requested')->count();
    }

    public function getPendingMaterialFinanceCount(): int
    {
        $user = auth()->user();
        if (!$user->isProgrammer() && !$user->hasPermission('approve_material_requisitions')) {
            return 0;
        }
        return \App\Models\MaterialRequisition::where('status', 'Pending Finance')->count();
    }

    public function getPendingProductRequestCount(): int
    {
        $user = auth()->user();
        if (!$user->isProgrammer() && !$user->hasPermission('review_product_requisitions')) {
            return 0;
        }
        return \App\Models\ProductRequisition::where('status', 'Requested')->count();
    }

    public function getPendingProductFinanceCount(): int
    {
        $user = auth()->user();
        if (!$user->isProgrammer() && !$user->hasPermission('approve_product_requisitions')) {
            return 0;
        }
        return \App\Models\ProductRequisition::where('status', 'Pending Finance')->count();
    }

    public function getPendingRepackLockCount(): int
    {
        $user = auth()->user();
        if (!$user->isProgrammer() && !$user->hasPermission('lock_repacks')) {
            return 0;
        }
        return \App\Models\Repack::where('kunci', '!=', 1)->count();
    }

    public function getPendingTallyCount(): int
    {
        $user = auth()->user();
        if (!$user->isProgrammer() && !$user->hasPermission('create_tallies')) {
            return 0;
        }
        return \App\Models\SalesOrder::where('status', \App\Models\SalesOrder::STATUS_WAITING)->count();
    }

    public function getPendingDeliveryPlanCount(): int
    {
        $user = auth()->user();
        if (!$user->isProgrammer() && !$user->hasPermission('edit_delivery_plans')) {
            return 0;
        }
        $tomorrow = now()->addDay()->toDateString();
        return \App\Models\DeliveryPlan::whereDate('delivery_date', $tomorrow)
            ->where(function ($query) {
                $query->whereNull('driver')
                    ->orWhere('driver', '')
                    ->orWhereNull('armada')
                    ->orWhere('armada', '')
                    ->orWhereNull('load_time');
            })
            ->count();
    }

    public function getPendingGrMaterialCount(): int
    {
        if (!auth()->user()->hasPermission('create_gr_materials')) {
            return 0;
        }
        return \App\Models\PurchaseMaterial::whereIn('status', ['pending', 'partial'])->count();
    }

    public function getPendingGrProductCount(): int
    {
        if (!auth()->user()->hasPermission('create_goods_receipt_products')) {
            return 0;
        }
        return \App\Models\PurchaseProduct::whereIn('status', ['pending', 'partial'])->count();
    }

    public function getPendingBoningLockCount(): int
    {
        if (!auth()->user()->hasPermission('lock_bonings')) {
            return 0;
        }
        return \App\Models\Boning::where('kunci', false)->count();
    }

    public function getPendingDeliveryOrderCount(): int
    {
        if (!auth()->user()->hasPermission('create_delivery_orders')) {
            return 0;
        }
        return \App\Models\Tally::where('status', 'locked')->whereDoesntHave('deliveryOrder')->count();
    }

    public function getPendingDeliveryReceiptCount(): int
    {
        if (!auth()->user()->hasPermission('view_delivery_receipts')) {
            return 0;
        }
        return \App\Models\DeliveryOrder::where('status', 'Ready')->count();
    }

    public function getPendingInvoiceExchangeCount(): int
    {
        $user = auth()->user();
        if (!$user->isProgrammer() && !$user->hasPermission('tukar_faktur')) {
            return 0;
        }
        return \App\Models\Invoice::whereNull('invoice_exchange_date')
            ->whereHas('customer', function ($query) {
                $query->where('invoice_exchange', true);
            })->count();
    }

    public function getPendingMutationCount(): int
    {
        if (!auth()->user()->hasPermission('view_mutations')) {
            return 0;
        }
        return \App\Models\Mutation::where('status', 'SENT')->count();
    }

    public function getPendingBeefStockTakeCount(): int
    {
        return \App\Models\StockTake::whereIn('status', ['DRAFT', 'IN_PROGRESS'])->count();
    }

    public function getPendingMaterialStockTakeCount(): int
    {
        return \App\Models\MaterialStockTake::whereIn('status', ['DRAFT', 'IN_PROGRESS'])->count();
    }

    public function getAging60DaysCount(): int
    {
        return \App\Models\BeefStock::where('status', 'IN_STOCK')
            ->where('pack_date', '<=', \Carbon\Carbon::now()->subDays(60))
            ->whereHas('grade', function ($q) {
                $q->where('name', 'like', '%CHILL%');
            })
            ->count();
    }
}
