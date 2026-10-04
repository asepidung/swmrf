<?php

namespace App\Helpers;

class BarcodeHelper
{
    /**
     * Get the origin name from a barcode string.
     *
     * @param string $barcode
     * @return string
     */
    public static function getOrigin($barcode)
    {
        // Barcode standar SWM: 28 digit (`BarcodeSegments`, sejak #486).
        if (! \App\Support\BarcodeSegments::isStandard($barcode)) {
            return '-UNIND';
        }

        $prefix = substr($barcode, 0, 1);
        
        $origins = [
            '1' => 'BONING',
            '2' => 'R-STCK',
            '3' => 'R-IMPT',
            '4' => 'R-RTRN',
            '5' => 'R-TRDG',
            '6' => 'RLB-TL',
            '7' => 'TRD-LC',
            '8' => 'TRD-IM',
            '0' => '-FOUND',
        ];

        return $origins[$prefix] ?? '-UNIND';
    }
}
