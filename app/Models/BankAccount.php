<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use HasFactory;

    /** Penanda akun kas tunai. Bukan rekening bank sungguhan. */
    public const CASH_INITIAL = 'KAS';

    protected $fillable = [
        'initial',
        'bank_name',
        'account_number',
        'account_holder',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Penanda baris saldo awal di buku kas.
     *
     * Saldo awal dicatat sebagai TRANSAKSI, bukan sebagai angka di master
     * data. Dengan begitu ia punya tanggal, keterangan, dan jejak yang sama
     * dengan uang lain yang bergerak -- dan saldo tetap punya satu sumber
     * kebenaran.
     */
    public const OPENING_BALANCE_REFERENCE = 'opening_balance';

    /**
     * Penanda baris penyesuaian kas.
     *
     * Saldo awal sifatnya SEKALI. Koreksi yang berulang bukan saldo awal --
     * itu penyesuaian, dan sengaja dibedakan supaya keduanya tidak tertukar
     * saat riwayat dibaca kembali berbulan-bulan kemudian.
     */
    public const ADJUSTMENT_REFERENCE = 'adjustment';

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function transactions()
    {
        return $this->hasMany(BankTransaction::class);
    }

    /**
     * Saldo rekening ini, DIHITUNG dari mutasinya.
     *
     * Dulu saldo disimpan sebagai kolom `bank_accounts.balance` yang
     * di-increment/decrement tiap ada pembayaran. Itu berarti ada DUA angka
     * yang mengaku benar -- kolomnya dan jumlah mutasinya -- dan begitu
     * keduanya berbeda, tidak ada cara menentukan mana yang salah tanpa
     * memeriksa satu per satu. Keputusan Project Owner: saldo tidak boleh
     * hidup di master data, persis seperti stok barang yang berkumpul di
     * tabelnya sendiri.
     *
     * Sekarang kolom itu tidak ada lagi, jadi tidak ada angka kedua yang
     * bisa menyimpang.
     */
    public function currentBalance(): float
    {
        $sums = $this->transactions()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as total_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) as total_out")
            ->first();

        return (float) $sums->total_in - (float) $sums->total_out;
    }

    /** Baris saldo awal rekening ini, bila sudah pernah disetel. */
    public function openingBalanceEntry(): ?BankTransaction
    {
        return $this->transactions()
            ->where('reference_type', self::OPENING_BALANCE_REFERENCE)
            ->first();
    }

    /**
     * Boleh menyetel saldo awal rekening ini?
     *
     * MEMBUAT saldo awal aman kapan pun, termasuk pada rekening yang sudah
     * punya mutasi -- dan itu justru kondisi normal di sini. Sistem ini
     * refactor dari aplikasi lama: hutang dan piutang sudah berjalan, jadi
     * pembukuan memang dimulai dari tengah dan titik awalnya baru dipasang
     * belakangan, dengan tanggal mundur ke cut-off.
     *
     * Yang berbahaya adalah MENGGESER titik awal yang sudah menjadi dasar
     * perhitungan. Karena itu batasnya bukan "rekening sudah dipakai",
     * melainkan "saldo awalnya sudah ada DAN sudah dipakai": selama belum
     * pernah diset ia selalu boleh dibuat, dan sesudahnya masih boleh
     * diperbaiki sampai ada mutasi lain menumpuk di atasnya.
     *
     * Koreksi setelah itu punya jalurnya sendiri -- Penyesuaian Kas -- yang
     * mencatat selisihnya beserta alasannya, bukan menulis ulang masa lalu.
     * Sama seperti stok fisik yang berbeda dari catatan: jawabannya Stock
     * Opname, bukan mengubah penerimaan barang yang pertama.
     */
    public function canSetOpeningBalance(): bool
    {
        if (! $this->openingBalanceEntry()) {
            return true;
        }

        return ! $this->hasEntriesBesidesOpeningBalance();
    }

    /** Rekening ini sudah punya mutasi selain saldo awalnya sendiri? */
    public function hasEntriesBesidesOpeningBalance(): bool
    {
        return $this->transactions()
            ->where(function ($query) {
                // Dikelompokkan: tanpa pembungkus ini, `orWhereNull` akan
                // lolos dari penyaring rekening dan memeriksa seluruh tabel.
                $query->where('reference_type', '!=', self::OPENING_BALANCE_REFERENCE)
                    ->orWhereNull('reference_type');
            })
            ->exists();
    }

    /**
     * Akun kas tunai, dibuat sekali dan dipakai seterusnya.
     *
     * Uang keluar SELALU harus tercatat, tunai maupun transfer, tapi
     * `bank_transactions.bank_account_id` tidak boleh NULL — sebuah kas
     * tunai memang sebuah akun, sama seperti rekening bank. Dikunci dengan
     * `->lockForUpdate()`, mengikuti pola generator nomor dokumen lain di
     * proyek ini, supaya dua pembayaran tunai bersamaan tidak menciptakan
     * dua baris KAS.
     */
    public static function cashAccount(): self
    {
        return \Illuminate\Support\Facades\DB::transaction(function () {
            $existing = static::where('initial', self::CASH_INITIAL)->lockForUpdate()->first();

            if ($existing) {
                return $existing;
            }

            return static::create([
                'initial' => self::CASH_INITIAL,
                'bank_name' => 'Kas Tunai',
                'account_number' => '-',
                'account_holder' => 'Wijaya Meat',
                'is_active' => true,
            ]);
        });
    }
}
