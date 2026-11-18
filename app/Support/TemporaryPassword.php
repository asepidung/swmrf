<?php

namespace App\Support;

/**
 * Kata sandi sementara untuk akun baru dan reset (keputusan Ayah, 18 November 2026): ACAK, hanya angka supaya
 * mudah disampaikan dan diingat, ditampilkan sekali ke admin, dan wajib diganti saat login pertama.
 */
class TemporaryPassword
{
    public static function generate(): string
    {
        return (string) random_int(10000000, 99999999);
    }
}
