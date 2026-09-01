<?php

namespace App\Console\Commands;

use App\Filament\Admin\Resources\GoodsReceiptMaterialResource;
use App\Models\GoodsReceiptMaterial;
use App\Models\GoodsReceiptProduct;
use App\Support\TaskNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Mengingatkan Goods Receipt yang sudah lewat sehari tetapi belum dikunci.
 *
 * KENAPA INI PENTING. Selama Goods Receipt belum dikunci, hutang kepada
 * pemasok TIDAK TERBENTUK. Barangnya sudah diterima dan pemasoknya sudah
 * menunggu, tetapi sistem tidak mencatat apa pun yang harus dibayar --
 * tanpa error, tanpa gejala, dan tanpa ada yang menyadarinya sampai
 * pemasoknya menagih.
 *
 * DUA PULUH EMPAT JAM, bukan langsung. Goods Receipt yang baru dibuat pagi
 * ini memang wajar belum dikunci; barangnya masih dihitung, labelnya masih
 * dicetak. Yang perlu ditanyakan adalah yang menginap.
 *
 * SATU RINGKASAN PER HARI, bukan satu notifikasi per dokumen. Hari dengan
 * sepuluh GR menggantung akan mengirim sepuluh notifikasi, dan orang
 * berhenti membacanya justru pada hari yang paling perlu dibaca. Mengikuti
 * keputusan yang sama pada NotifyDuePayables.
 *
 * Kalau tidak ada satu pun yang menggantung, perintah ini TIDAK mengirim
 * apa-apa. Notifikasi "hari ini aman" setiap pagi akan mengubah lonceng
 * menjadi kebisingan yang diabaikan.
 */
class NotifyUnlockedGoodsReceipts extends Command
{
    /**
     * Umur minimal sebelum sebuah Goods Receipt dianggap menggantung.
     *
     * Dihitung dari waktu pembuatannya. Yang dibuat pagi ini tidak ikut
     * ditanyakan besok pagi kalau umurnya belum genap sehari.
     */
    public const GRACE_HOURS = 24;

    /**
     * Jejak terakhir perintah ini berjalan.
     *
     * Dibaca Dashboard. Tanpa penanda ini, cron yang mati atau lupa dipasang
     * TIDAK menghasilkan gejala apa pun: tidak ada error, tidak ada
     * notifikasi, dan tidak ada yang menyadari bahwa peringatannya sudah lama
     * berhenti terkirim.
     */
    public const LAST_RUN_CACHE_KEY = 'goods_receipts.unlocked_reminder.last_run';

    protected $signature = 'goods-receipts:notify-unlocked';

    protected $description = 'Kirim satu ringkasan harian tentang Goods Receipt yang sudah menginap tetapi belum dikunci';

    public function handle(): int
    {
        $summary = static::summary();

        // Dicatat lebih dulu, apa pun hasilnya. Yang perlu diketahui
        // Dashboard adalah "pemeriksaannya berjalan", bukan "ada yang
        // dikirim" -- hari tanpa GR menggantung memang tidak mengirim apa-apa.
        Cache::forever(static::LAST_RUN_CACHE_KEY, now()->toIso8601String());

        if ($summary['total'] === 0) {
            $this->info('Tidak ada Goods Receipt yang menggantung.');

            return self::SUCCESS;
        }

        $sent = TaskNotifier::notifyPermissionHolders(
            // Orang yang benar-benar bisa mengunci, bukan semua yang bisa
            // melihat daftarnya.
            permissions: ['edit_goods_receipt_products', 'edit_gr_materials'],
            title: __('Goods receipts not locked'),
            body: static::describe($summary),
            url: GoodsReceiptMaterialResource::getUrl('index'),
            // Tag yang sama setiap hari: notifikasi hari ini menggantikan
            // yang kemarin di layar, alih-alih menumpuk jadi antrean lama.
            tag: 'goods-receipts-unlocked',
        );

        $this->info("Ringkasan terkirim ke {$sent} penerima: ".static::describe($summary));

        return self::SUCCESS;
    }

    /**
     * Hitungan Goods Receipt yang sudah menginap tetapi belum dikunci.
     *
     * @return array{beef:int, material:int, total:int}
     */
    public static function summary(): array
    {
        $batas = now()->subHours(static::GRACE_HOURS);

        $beef = GoodsReceiptProduct::query()
            ->where('is_locked', false)
            ->where('created_at', '<=', $batas)
            ->count();

        $material = GoodsReceiptMaterial::query()
            ->where('is_locked', false)
            ->where('created_at', '<=', $batas)
            ->count();

        return [
            'beef' => $beef,
            'material' => $material,
            'total' => $beef + $material,
        ];
    }

    /**
     * Ringkasan dalam satu kalimat, hanya menyebut yang benar-benar ada.
     *
     * @param  array{beef:int, material:int, total:int}  $summary
     */
    public static function describe(array $summary): string
    {
        $parts = [];

        if ($summary['beef'] > 0) {
            $parts[] = __(':count beef receipts', ['count' => $summary['beef']]);
        }

        if ($summary['material'] > 0) {
            $parts[] = __(':count material receipts', ['count' => $summary['material']]);
        }

        return __(':items are still unlocked, so no payable exists for them yet.', [
            'items' => implode(', ', $parts),
        ]);
    }
}
