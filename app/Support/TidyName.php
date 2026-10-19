<?php

namespace App\Support;

/**
 * Merapikan penulisan NAMA perusahaan/orang saat import (keputusan Ayah, 19 Oktober 2026):
 *  - huruf besar semua, spasi ganda dirapikan;
 *  - badan usaha PT dan CV tanpa titik dan SELALU di belakang nama ("PT. ARDANA" -> "ARDANA PT"), supaya daftar
 *    urut abjad menurut nama intinya (pola yang sama dengan "ASEP BPK");
 *  - setelah tanda baca ada satu spasi ("CV.RIZAVIN" -> "RIZAVIN CV"), tanpa spasi di dalam kurung ("( BASS )" -> "(BASS)");
 *  - gelar seperti "H." dan BPK/IBU dibiarkan.
 */
class TidyName
{
    public static function company(?string $name): string
    {
        $n = mb_strtoupper(trim((string) $name));

        // Tanda baca: tanpa spasi di dalam kurung, satu spasi sesudah koma/titik/titik dua.
        $n = (string) preg_replace('/\(\s+/', '(', $n);
        $n = (string) preg_replace('/\s+\)/', ')', $n);

        // PT/CV: titiknya dibuang lebih dulu ("PT.X", "PT. X", "P.T." tidak dipakai).
        $n = (string) preg_replace('/\b(PT|CV)\s*\.\s*/', '$1 ', $n);
        $n = (string) preg_replace('/\s+(PT|CV)\.(?=\s|$)/', ' $1', $n);

        $n = (string) preg_replace('/([,;:])(?=\S)/', '$1 ', $n);
        $n = (string) preg_replace('/(?<=[A-Z])\.(?=[A-Z])/', '. ', $n);
        $n = (string) preg_replace('/\s+/', ' ', trim($n));

        // PT/CV di depan pindah ke belakang (kalau belum ada di belakang).
        if (preg_match('/^(PT|CV)\s+(.+)$/', $n, $m) && ! preg_match('/\s(PT|CV)$/', $m[2])) {
            $n = $m[2].' '.$m[1];
        }

        return $n;
    }
}
