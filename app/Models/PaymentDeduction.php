<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentDeduction extends Model
{
    /**
     * Biaya yang diambil bank atas transfernya.
     *
     * Milik TRANSFERNYA, bukan milik invoice mana pun -- jadi `invoice_id`
     * memang dikosongkan, dan potongannya larut ke seluruh pembayaran.
     */
    public const TYPE_BANK_FEE = 'bank_fee';

    /**
     * Klaim promo dari pelanggan.
     *
     * Hampir selalu melekat pada SATU invoice tertentu, karena promonya
     * dijanjikan atas penjualan tertentu. Karena itu jenis inilah yang paling
     * butuh `invoice_id` diisi.
     */
    public const TYPE_PROMOTION = 'promotion';

    /** Selebihnya, termasuk seluruh baris lama yang jenisnya tidak pernah dicatat. */
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'payment_id',
        'type',
        'invoice_id',
        'description',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Pilihan jenis potongan.
     *
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_BANK_FEE => __('Bank Fee'),
            self::TYPE_PROMOTION => __('Promotion'),
            self::TYPE_OTHER => __('Other'),
        ];
    }

    public static function typeLabel(?string $type): string
    {
        return static::typeOptions()[$type] ?? (string) $type;
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    /**
     * Invoice yang ditunjuk potongan ini, kalau ada.
     *
     * Kosong berarti potongannya milik transfernya sendiri dan dibagi bersama
     * seluruh pembayaran -- perlakuan yang benar untuk biaya bank.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
