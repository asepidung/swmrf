<?php

namespace App\Support;

/**
 * Jenis notifikasi tugas -> gambar besar di notifikasi (sisi kanan).
 *
 * Android sendiri menaruh ikon aplikasi di sisi kiri; bila gambar kanan memakai logo yang sama, logo tampil dua
 * kali. Gambar kanan karena itu dibuat piktogram menurut jenisnya (papan catatan = permintaan, koin = pembayaran,
 * kardus = stok/barang, lonceng = lainnya), supaya sekilas terbaca notifikasi itu tentang apa. Gambar harus penuh
 * warna sampai tepi: gambar transparan dirender Android (HyperOS) sebagai kotak biru polos.
 *
 * Jenis dibaca dari AWALAN `tag` yang sudah dipakai semua pengirim, jadi pemanggil tidak perlu diubah.
 */
class NotificationKind
{
    public const REQUEST = 'permintaan';

    public const PAYMENT = 'pembayaran';

    public const STOCK = 'stok';

    public const GENERAL = 'umum';

    /** Awalan tag => jenis. Yang tidak terdaftar jatuh ke GENERAL. */
    private const BY_TAG_PREFIX = [
        'material-request-' => self::REQUEST,
        'beef-request-' => self::REQUEST,
        'payables-due' => self::PAYMENT,
        'goods-receipts-unlocked' => self::STOCK,
        'sales-order-' => self::STOCK,
    ];

    public static function forTag(?string $tag): string
    {
        foreach (self::BY_TAG_PREFIX as $prefix => $kind) {
            if ($tag !== null && str_starts_with($tag, $prefix)) {
                return $kind;
            }
        }

        return self::GENERAL;
    }

    public static function iconFor(?string $tag): string
    {
        return '/img/notif/notif-'.self::forTag($tag).'.png';
    }
}
