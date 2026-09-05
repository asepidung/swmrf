<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinancialLoss extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Modul yang bisa menerbitkan kerugian, sebagai SATU daftar.
     *
     * Saringan di layar dulu menuliskan pilihannya sendiri dan hanya memuat
     * 'Cattle Weighing'. Susut kirim sudah ditulis sejak lama dengan sumber
     * 'Delivery Order', tetapi tidak pernah muncul di saringan itu -- barisnya
     * ada di tabel, hanya tidak bisa dipilih. Daftar yang ditulis tangan
     * selalu ketinggalan dari yang benar-benar ditulis kode.
     *
     * Nilainya sudah tersimpan apa adanya di ribuan baris lama, jadi
     * teksnya TIDAK boleh berubah -- yang berubah cuma tempat menyebutnya.
     */
    public const SUMBER_TIMBANG_SAPI = 'Cattle Weighing';

    public const SUMBER_SURAT_JALAN = 'Delivery Order';

    public const SEMUA_SUMBER = [
        self::SUMBER_TIMBANG_SAPI,
        self::SUMBER_SURAT_JALAN,
    ];

    protected $fillable = [
        'lossable_type',
        'lossable_id',
        'date',
        'transaction_type',
        'reference_number',
        'amount',
        'quantity',
        'unit',
        'note'
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'quantity' => 'decimal:2',
    ];

    /**
     * Kerugian yang JUMLAHNYA diketahui tetapi RUPIAHNYA belum.
     *
     * Susut kirim tahu persis berapa kilogram yang hilang, tetapi menilainya
     * butuh HPP -- dan HPP menunggu B.O.M. Sampai itu tiba, `amount` tetap nol
     * sementara `quantity` sudah terisi. Ini yang membedakannya dari kerugian
     * yang memang bernilai nol.
     */
    public function isNotPricedYet(): bool
    {
        return (float) $this->amount <= 0 && (float) $this->quantity > 0;
    }

    
    public function lossable(): MorphTo
    {
        return $this->morphTo();
    }
}
