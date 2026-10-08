<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Mengunci Boning/Repack yang drylog-nya belum diisi.
     *
     * Sejak #509 drylog WAJIB diisi sebelum dokumen bisa dikunci. Test yang
     * tidak sedang menguji drylog memakai ini supaya tetap fokus pada hal yang
     * diuji (susut, HPP, kerugian) -- drylog 0 diisi bila belum ada.
     */
    protected function lockWithDrylog(Model $document): void
    {
        if (! $document->drylogWasFilled()) {
            $document->forceFill(['drylog_qty' => 0])->save();
        }

        $document->lock();
    }
}
