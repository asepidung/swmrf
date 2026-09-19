<?php

namespace App\Models;

use App\Support\DocumentNumber;
use App\Support\InvoiceTotals;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesReturn extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'return_number',
        'return_date',
        'delivery_order_id',
        'sales_return_plan_id',
        'credit_amount',
        'customer_id',
        'note',
        'status',
        'created_by',
    ];

    protected $casts = [
        'return_date' => 'date',
        'credit_amount' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Issue #451, keputusan Owner 17 September: retur TIDAK BOLEH
            // dibuat tanpa plan -- "Tarik Plan" satu-satunya jalur masuk,
            // dan ini lapis kedua yang menegakkannya di server (lapis
            // pertama tombol Create yang sudah dihilangkan dari layar).
            if (empty($model->sales_return_plan_id)) {
                throw new \Exception(__('A sales return can only be created by pulling a submitted plan.'));
            }

            $plan = SalesReturnPlan::find($model->sales_return_plan_id);

            if (! $plan || $plan->status !== SalesReturnPlan::STATUS_SUBMITTED) {
                throw new \Exception(__('The chosen plan is not submitted, so it cannot be pulled.'));
            }

            if ($plan->salesReturn()->exists()) {
                throw new \Exception(__('This plan has already been pulled into a sales return.'));
            }

            if (empty($model->return_number)) {
                $model->return_number = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'return_number',
                    prefix: 'SR#'.date('y'),
                    padding: 3,
                );
            }
            if (empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
        });

        static::deleting(function (self $model) {
            // Hapus retur mengembalikan plan-nya ke Submitted, SUPAYA bisa
            // ditarik ulang -- tapi hanya kalau plan-nya sempat Received
            // (retur pernah di-approve lalu di-unlock lalu dihapus).
            // Retur Draft yang belum pernah di-approve tidak pernah
            // mengubah status plan-nya sama sekali, jadi tidak ada yang
            // perlu dibalik.
            if ($model->plan && $model->plan->status === SalesReturnPlan::STATUS_RECEIVED) {
                $model->plan->markSubmitted();
            }
        });

        static::deleted(function (self $model) {
            $model->isForceDeleting() ? $model->financialLoss()->forceDelete() : $model->financialLoss()->delete();
        });

        static::restored(function (self $model) {
            if ($model->financialLoss()->withTrashed()->exists()) {
                $model->financialLoss()->withTrashed()->restore();
            }
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SalesReturnPlan::class, 'sales_return_plan_id');
    }

    public function financialLoss(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(FinancialLoss::class, 'lossable');
    }

    /** Total fisik yang benar-benar di-scan untuk satu produk di retur ini. */
    public function physicalWeightFor(int $productId): float
    {
        return (float) $this->items()->where('product_id', $productId)->sum('weight');
    }

    /**
     * Ringkasan klaim vs fisik per produk -- satu baris per produk yang
     * MUNCUL di salah satu sisi (diklaim di plan ATAU benar-benar
     * di-scan), bukan gabungan keduanya secara diam-diam.
     *
     * Keputusan Owner 20 September 2026 (issue #476): fisik mengikuti
     * yang datang, uang mengikuti klaim -- produk yang di-scan tapi
     * tidak pernah diklaim ditandai `received_without_claim` di sini,
     * dipakai layar (`InputReturnItems`) dan cetakan supaya sales/
     * finance tahu ada yang perlu diperbaiki manual, tanpa harus
     * membandingkan dua tabel sendiri.
     *
     * @return \Illuminate\Support\Collection<int, array{product_id: int, product_name: string, claimed: float, physical: float, variance: float, received_without_claim: bool}>
     */
    public function claimVsPhysicalSummary(): \Illuminate\Support\Collection
    {
        // Retur TANPA plan sama sekali (data lama) tidak punya konsep
        // klaim -- menampilkan "DITERIMA TANPA KLAIM" untuk SEMUA
        // produknya cuma akan membingungkan, bukan menerangkan apa pun.
        if (! $this->plan) {
            return collect();
        }

        $klaimPerProduk = $this->plan->items()->selectRaw('product_id, SUM(claimed_weight) as total')->groupBy('product_id')->pluck('total', 'product_id');

        $fisikPerProduk = $this->items->groupBy('product_id')->map(fn ($items) => (float) $items->sum('weight'));

        $productIds = $klaimPerProduk->keys()->merge($fisikPerProduk->keys())->unique();

        return $productIds->map(function ($productId) use ($klaimPerProduk, $fisikPerProduk) {
            $klaim = (float) ($klaimPerProduk[$productId] ?? 0.0);
            $fisik = (float) ($fisikPerProduk[$productId] ?? 0.0);

            return [
                'product_id' => (int) $productId,
                'product_name' => Product::find($productId)?->name ?? '#'.$productId,
                'claimed' => $klaim,
                'physical' => $fisik,
                'variance' => round($klaim - $fisik, 2),
                'received_without_claim' => $klaim <= 0 && $fisik > 0,
            ];
        })->sortBy('product_name')->values();
    }

    /**
     * Selisih klaim - fisik, per produk, jadi SATU FinancialLoss saat
     * approve (issue #451). Kredit tetap ikut klaim penuh (attachToBill())
     * -- selisihnya kerugian TERPISAH, bukan pengurang kredit.
     */
    private function recordClaimVarianceLoss(): void
    {
        if (! $this->plan) {
            $this->financialLoss()->delete();

            return;
        }

        $totalVarianceKg = 0.0;
        $totalVarianceAmount = 0.0;
        $catatan = [];

        $klaimPerProduk = $this->plan->items()
            ->selectRaw('product_id, SUM(claimed_weight) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        foreach ($klaimPerProduk as $productId => $klaim) {
            $selisih = round((float) $klaim - $this->physicalWeightFor((int) $productId), 2);

            if ($selisih <= 0) {
                continue;
            }

            $hargaPerKg = (float) ($this->items()->where('product_id', $productId)->value('unit_price') ?? 0);

            $totalVarianceKg += $selisih;
            $totalVarianceAmount += round($selisih * $hargaPerKg, 0);

            $produkNama = Product::find($productId)?->name ?? '#'.$productId;
            $catatan[] = "{$produkNama}: {$selisih} kg";
        }

        if ($totalVarianceKg <= 0) {
            $this->financialLoss()->delete();

            return;
        }

        $this->financialLoss()->updateOrCreate(
            ['transaction_type' => FinancialLoss::SUMBER_RETUR, 'reference_number' => $this->return_number],
            [
                'date' => $this->return_date,
                'amount' => round($totalVarianceAmount, 2),
                'quantity' => round($totalVarianceKg, 2),
                'unit' => 'Kg',
                'note' => __('Claimed but not physically received: :detail', ['detail' => implode(', ', $catatan)]),
            ],
        );
    }

    /**
     * Setujui retur ini: seluruh barangnya masuk ke stok.
     *
     * @throws \RuntimeException
     */
    public function approve(): void
    {
        if ($this->status !== 'Draft') {
            throw new \RuntimeException(__('Only a draft return can be approved.'));
        }

        if ($this->items->isEmpty()) {
            throw new \RuntimeException(__('This return has no item yet.'));
        }

        // Klaim ada tapi fisik nol -- barangnya tidak pernah datang,
        // ditolak di sini (issue #451, keputusan Owner 17 September;
        // DIPERTAHANKAN oleh issue #476). Produk yang di-scan tapi TIDAK
        // diklaim sebaliknya boleh lewat sejak issue #476 -- diterima ke
        // stok dengan kredit 0, tidak lagi ditolak saat scan.
        if ($this->plan) {
            foreach ($this->plan->items as $planItem) {
                if ((float) $planItem->claimed_weight > 0 && $this->physicalWeightFor($planItem->product_id) <= 0) {
                    throw new \RuntimeException(__('Claimed product :product never physically arrived, so this return cannot be approved.', [
                        'product' => $planItem->product?->name ?? '#'.$planItem->product_id,
                    ]));
                }
            }
        }

        DB::transaction(function (): void {
            $this->update(['status' => 'Approved']);

            foreach ($this->items as $item) {
                BeefStock::create([
                    'barcode' => $item->barcode,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'grade_id' => $item->grade_id,
                    'weight' => $item->weight,
                    'qty_pcs' => $item->qty_pcs,
                    'ph_level' => $item->ph_level,
                    'pack_date' => $item->pack_date,
                    'exp_date' => $item->exp_date,
                    'origin' => $item->origin,
                    'status' => 'IN_STOCK',
                    'note' => 'Sales Return '.$this->return_number,
                ]);

                BeefStockMovement::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'condition' => $item->grade_id,
                    'barcode' => $item->barcode,
                    'transaction_type' => 'SALES_RETURN',
                    'reference_document' => $this->return_number,
                    'weight_in' => $item->weight,
                    'pcs_in' => $item->qty_pcs,
                    'created_by' => Auth::id(),
                    'note' => 'Sales Return from Customer',
                ]);
            }

            // Barangnya sudah kembali ke gudang. Sekarang uangnya.
            $this->attachToBill();

            // Selisih klaim-fisik jadi kerugian TERPISAH -- kreditnya
            // sendiri tetap ikut klaim penuh (attachToBill() di atas).
            $this->recordClaimVarianceLoss();

            $this->plan?->markReceived();
        });
    }

    /**
     * Buka kunci retur ini: seluruh barangnya ditarik kembali dari stok.
     *
     * SATU RUMAH untuk keduanya. Rutin ini dulu disalin utuh di halaman Edit
     * DAN halaman View -- termasuk celah izinnya, sehingga menambal yang satu
     * meninggalkan yang lain tetap terbuka. Pola yang sama sudah pernah
     * ditemukan pada saldo hutang yang disalin enam kali dan rumus tagihan
     * yang disalin lima kali.
     *
     * Barangnya diperiksa lebih dulu SEMUANYA, baru ditarik. Menarik separuh
     * lalu berhenti di tengah karena satu barang sudah terlanjur dikirim lagi
     * meninggalkan stok yang tidak cocok dengan dokumen mana pun.
     *
     * @throws \RuntimeException
     */
    public function unlock(): void
    {
        if ($this->status !== 'Approved') {
            throw new \RuntimeException(__('Only an approved return can be unlocked.'));
        }

        DB::transaction(function (): void {
            foreach ($this->items as $item) {
                $stock = BeefStock::where('barcode', $item->barcode)->lockForUpdate()->first();

                if (! $stock) {
                    throw new \RuntimeException(__('Item :barcode is no longer in stock (already used or shipped).', [
                        'barcode' => $item->barcode,
                    ]));
                }

                if ($stock->status !== 'IN_STOCK') {
                    throw new \RuntimeException(__('Item :barcode is no longer in the warehouse (status: :status).', [
                        'barcode' => $item->barcode,
                        'status' => $stock->status,
                    ]));
                }
            }

            foreach ($this->items as $item) {
                BeefStockMovement::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'condition' => $item->grade_id,
                    'barcode' => $item->barcode,
                    'transaction_type' => 'CANCEL_SALES_RETURN',
                    'reference_document' => $this->return_number,
                    'weight_out' => $item->weight,
                    'pcs_out' => $item->qty_pcs,
                    'created_by' => Auth::id(),
                    'note' => 'Unlock/Cancel Sales Return',
                ]);

                BeefStock::where('barcode', $item->barcode)->delete();
            }

            $this->detachFromBill();

            // Kerugian selisih klaim-fisik ikut dibalik -- lahir saat
            // approve, jadi tidak berlaku lagi begitu approve-nya dibuka.
            $this->financialLoss()->delete();

            $this->update(['status' => 'Draft']);

            // Plan TETAP `Received` -- keputusan Owner (issue #451 §2):
            // retur masih ada, cuma dibuka kuncinya. Hanya MENGHAPUS
            // retur yang mengembalikan plan ke `Submitted` (lihat
            // `deleting` di boot()).
        });
    }

    // =================================================================
    // Sisi uang: retur memotong tagihan pelanggan
    // =================================================================

    /**
     * Invoice yang dipotong retur ini -- bisa LEBIH DARI SATU.
     *
     * Tautannya ada di kartonnya, bukan di returnya. Satu retur boleh memuat
     * barang dari beberapa kiriman sekaligus, dan tiap kiriman punya
     * invoicenya sendiri. Project Owner, 4 September 2026: pelanggan sebesar
     * Lion Superindo memang mengembalikan barang dari beberapa kiriman dalam
     * satu kali jalan, dan justru untuk itulah retur tanpa surat jalan
     * ("Unidentified Delivery") disediakan.
     *
     * @return \Illuminate\Support\Collection<int, Invoice>
     */
    public function billsReduced(): \Illuminate\Support\Collection
    {
        return Invoice::query()
            ->whereIn('id', $this->items()->whereNotNull('invoice_id')->pluck('invoice_id')->unique())
            ->get();
    }

    /**
     * Rekam harga jual tiap karton, lalu tempelkan nilainya ke invoicenya
     * masing-masing.
     *
     * Harganya DI-SNAPSHOT, bukan dibaca ulang tiap kali dibutuhkan. Harga
     * bergerak -- lewat Price List, lewat diskon pelanggan, lewat Sales Order
     * berikutnya -- dan nota retur yang ikut bergerak berarti angka yang sudah
     * disepakati berubah sendiri di belakang punggung orang.
     *
     * Karton yang kirimannya BELUM ditagihkan tetap dinilai tetapi belum
     * menempel ke mana-mana. Ia menunggu, dan invoice yang lahir kemudian
     * untuk surat jalan itu yang memungutnya.
     *
     * BERAT FISIK dan BERAT YANG DIKREDITKAN dipisah, dan itu inti dari
     * rutin ini. Kita mengirim satu box 20,00 kg; pelanggan menimbang ulang
     * dan mendapat 19,80 kg, dan angka itulah yang ditagihkan. Saat boxnya
     * kembali, 20,00 kg masuk gudang -- itu yang benar-benar ada di sana --
     * tetapi yang boleh dikreditkan tetap 19,80 kg.
     *
     * Versi sebelumnya MENOLAK retur semacam itu, karena mengira berat kirim
     * dan berat tagih harus sama. Project Owner menegaskan selisih timbangan
     * adalah alur yang biasa, bukan penyimpangan; penolakannya yang salah,
     * bukan returnya. Retur berlebihan yang sungguhan tetap tertahan di pintu
     * masuk: barcode yang tidak pernah dikirim ditolak, dan barcode yang sama
     * tidak bisa dipindai dua kali.
     */
    public function attachToBill(): void
    {
        $total = 0.0;
        $invoices = [];

        // Sisa jatah kredit per (invoice, produk), dipakai bersama oleh semua
        // karton di retur ini supaya dua baris tidak sama-sama memakai jatah
        // yang sama.
        $sisaJatah = [];

        // Dasar kredit sekarang KLAIM per produk (issue #451, keputusan
        // Owner 17 September), bukan berat fisik kartonnya -- "kredit =
        // qty klaim di plan", walau fisiknya kurang (selisihnya jadi
        // FinancialLoss terpisah lewat recordClaimVarianceLoss(), bukan
        // pengurang kredit). Kalau ada LEBIH dari satu karton untuk produk
        // yang sama, klaimnya dibagi proporsional menurut berat fisik
        // masing-masing karton -- tidak ada dasar lain untuk membaginya.
        // Retur TANPA plan (sebelum fitur ini ada) jatuh kembali ke kredit
        // berbasis fisik apa adanya, persis seperti sebelumnya.
        //
        // Susulan issue #476 (20 September 2026): scan tidak lagi menolak
        // produk di luar plan (fisik ikut yang datang), jadi sebuah
        // produk BISA muncul di sini dengan klaim 0 padahal plan-nya ADA
        // -- beda dari kasus "retur tanpa plan sama sekali" di atas. Kredit
        // produk begini 0 (uang ikut klaim), bukan jatuh ke fisik --
        // lihat percabangan `if (! $this->plan)` di bawah.
        $klaimPerProduk = $this->plan
            ? $this->plan->items()->selectRaw('product_id, SUM(claimed_weight) as total')->groupBy('product_id')->pluck('total', 'product_id')
            : collect();
        $fisikPerProduk = $this->items->groupBy('product_id')->map(fn ($items) => (float) $items->sum('weight'));

        foreach ($this->items as $item) {
            $invoice = $item->billItWasChargedOn();
            [$perKg, $hargaPenuh] = $this->sellingPriceFor($item, $invoice);

            $beratFisik = (float) $item->weight;
            $klaimProduk = (float) ($klaimPerProduk[$item->product_id] ?? 0.0);
            $fisikProduk = (float) ($fisikPerProduk[$item->product_id] ?? 0.0);

            if (! $this->plan) {
                // Retur lama, sebelum fitur plan ada -- kredit berbasis
                // fisik apa adanya, persis seperti sebelumnya.
                $beratKredit = $beratFisik;
            } elseif ($klaimProduk > 0 && $fisikProduk > 0) {
                $beratKredit = round($klaimProduk * ($beratFisik / $fisikProduk), 2);
            } else {
                // Keputusan Owner 20 September 2026 (issue #476): ada
                // plan, tapi produk ini TIDAK diklaim -- diterima apa
                // adanya (fisik ikut yang datang), tapi kreditnya 0
                // (uang ikut klaim). Ditandai "DITERIMA TANPA KLAIM" di
                // ringkasan (lihat `claimVsPhysicalSummary()`) supaya
                // sales/finance memperbaiki manual, bukan kredit diam-diam.
                $beratKredit = 0.0;
            }

            if ($invoice) {
                $kunci = $invoice->getKey().':'.$item->product_id;

                if (! array_key_exists($kunci, $sisaJatah)) {
                    $sisaJatah[$kunci] = max(
                        $invoice->billedWeightFor((int) $item->product_id)
                            - $invoice->returnedWeightFor((int) $item->product_id, $this->getKey()),
                        0,
                    );
                }

                $beratKredit = min($beratKredit, $sisaJatah[$kunci]);
                $sisaJatah[$kunci] = round($sisaJatah[$kunci] - $beratKredit, 2);
            }

            $beratKredit = round($beratKredit, 2);

            // Nilainya dihitung dari berat yang DIKREDITKAN. Kalau sama
            // persis dengan fisiknya -- keadaan biasa saat klaim dan fisik
            // sama -- hasilnya persis harga penuhnya, menghindari selisih
            // pembulatan.
            $jumlah = $beratKredit === round($beratFisik, 2)
                ? $hargaPenuh
                : round($beratKredit * $perKg, 0);

            $item->forceFill([
                'invoice_id' => $invoice?->getKey(),
                'unit_price' => $perKg,
                'credited_weight' => $beratKredit,
                'line_amount' => $jumlah,
            ])->save();

            $total += $jumlah;

            if ($invoice) {
                $invoices[$invoice->getKey()] = $invoice;
            }
        }

        $this->forceFill(['credit_amount' => round($total, 2)])->save();

        foreach ($invoices as $invoice) {
            $invoice->settleAfterCreditNote();
        }
    }

    /**
     * Lepaskan potongannya kembali dari semua invoice yang tersentuh.
     *
     * Alokasi pembayaran yang sudah TERLANJUR dilepas tidak dipasang kembali
     * di sini. Uang itu sekarang menjadi deposit pelanggan, dan dipakai lagi
     * lewat halaman Terima Pembayaran seperti lebih bayar biasa. Memasangnya
     * kembali otomatis berarti menebak pembayaran mana yang dulu menutup
     * invoice mana, padahal jejaknya sudah tidak ada.
     */
    public function detachFromBill(): void
    {
        $invoices = $this->billsReduced();

        $this->items()->update([
            'invoice_id' => null,
            'credited_weight' => null,
            'line_amount' => 0,
        ]);
        $this->forceFill(['credit_amount' => 0])->save();

        foreach ($invoices as $invoice) {
            $invoice->settleAfterCreditNote();
        }
    }

    /**
     * Harga jual satu karton retur: per kg, dan jumlah barisnya.
     *
     * Dua sumber, dengan urutan yang tidak boleh dibalik:
     *
     *  1. baris INVOICE untuk produk yang sama. `amount / weight` sudah
     *     memperhitungkan diskon barisnya, jadi yang dikembalikan kepada
     *     pelanggan persis sebesar yang ditagihkan kepadanya;
     *  2. baris SALES ORDER dari kiriman kartonnya sendiri, lewat
     *     `InvoiceTotals::line()` -- rumus yang sama persis dengan yang
     *     dipakai `InvoiceResource::billableLines()` untuk melahirkan invoice.
     *     Dipakai kalau invoicenya belum ada, supaya angkanya tidak bisa
     *     berbeda dari invoice yang akan terbit.
     *
     * Kalau produknya tidak ketemu di keduanya, harganya nol dan terbaca nol
     * di layar. Itu bisa terjadi pada barang yang ditimbang ulang dengan
     * produk yang berbeda dari yang dikirim. Menebak harganya lebih buruk
     * daripada menunjukkan bahwa ia belum berharga.
     *
     * @return array{0: float, 1: float} [harga per kg, jumlah baris]
     */
    private function sellingPriceFor(SalesReturnItem $item, ?Invoice $invoice): array
    {
        $berat = (float) $item->weight;

        if ($invoice) {
            $baris = $invoice->items->firstWhere('product_id', $item->product_id);

            if ($baris && (float) $baris->weight > 0) {
                $perKg = (float) $baris->amount / (float) $baris->weight;

                return [round($perKg, 2), round($berat * $perKg, 0)];
            }
        }

        // Sales Order diambil dari kiriman KARTONNYA, bukan dari surat jalan
        // yang tertulis di returnya. Retur tanpa surat jalan pun tetap
        // berharga, dan retur lintas kiriman memakai harga masing-masing.
        $salesOrderId = $item->originDelivery()?->sales_order_id;

        if ($salesOrderId) {
            $baris = SalesOrderItem::query()
                ->where('sales_order_id', $salesOrderId)
                ->where('product_id', $item->product_id)
                ->first();

            if ($baris) {
                $hasil = InvoiceTotals::line($berat, (float) $baris->price, (float) $baris->discount);
                $jumlah = (float) $hasil['amount'];

                return [$berat > 0 ? round($jumlah / $berat, 2) : 0.0, $jumlah];
            }
        }

        return [0.0, 0.0];
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Laporan QC yang mendampingi dokumen ini. */
    public function qcReports(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Models\QcReport::class, 'reportable');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}
