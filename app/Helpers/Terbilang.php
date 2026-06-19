<?php

namespace App\Helpers;

class Terbilang
{
    public static function convert($angka)
    {
        $angka = intval($angka);

        $bilangan = array(
            '',
            'Satu',
            'Dua',
            'Tiga',
            'Empat',
            'Lima',
            'Enam',
            'Tujuh',
            'Delapan',
            'Sembilan',
            'Sepuluh',
            'Sebelas'
        );

        if ($angka < 12) {
            return $bilangan[$angka];
        } elseif ($angka < 20) {
            return $bilangan[$angka - 10] . ' Belas';
        } elseif ($angka < 100) {
            return $bilangan[intval($angka / 10)] . ' Puluh ' . self::convert($angka % 10);
        } elseif ($angka < 200) {
            return 'Seratus ' . self::convert($angka - 100);
        } elseif ($angka < 1000) {
            return $bilangan[intval($angka / 100)] . ' Ratus ' . self::convert($angka % 100);
        } elseif ($angka < 2000) {
            return 'Seribu ' . self::convert($angka - 1000);
        } elseif ($angka < 1000000) {
            return self::convert(intval($angka / 1000)) . ' Ribu ' . self::convert($angka % 1000);
        } elseif ($angka < 1000000000) {
            return self::convert(intval($angka / 1000000)) . ' Juta ' . self::convert($angka % 1000000);
        } elseif ($angka < 1000000000000) {
            return self::convert(intval($angka / 1000000000)) . ' Miliar ' . self::convert($angka % 1000000000);
        } else {
            return 'Angka terlalu besar';
        }
    }

    public static function convertDecimal($angka)
    {
        $angka = number_format((float)$angka, 2, '.', '');
        list($bilangan_bulat, $desimal) = explode('.', $angka);

        $hasil = trim(self::convert($bilangan_bulat));

        if ((int)$desimal > 0) {
            $hasil .= ' Koma ';
            foreach (str_split($desimal) as $digit) {
                $hasil .= ($digit == '0') ? 'Nol ' : self::convert($digit) . ' ';
            }
        }

        return trim($hasil) . ' Rupiah';
    }
}
