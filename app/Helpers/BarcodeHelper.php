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
        $length = strlen($barcode);
        
        // SWM standard barcode is 26 digits
        if ($length !== 26) {
            return 'UNIND';
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
        ];

        return $origins[$prefix] ?? 'UNIND';
    }
}
