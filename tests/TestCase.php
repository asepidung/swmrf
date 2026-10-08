<?php

namespace Tests;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
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
            $material = Material::firstOrCreate(
                ['name' => 'DRYLOG'],
                [
                    'material_category_id' => MaterialCategory::firstOrCreate(['name' => 'PACKAGING'])->id,
                    'material_unit_id' => MaterialUnit::firstOrCreate(['name' => 'IKAT'])->id,
                    'min_stock' => 0,
                    'is_active' => true,
                ],
            );

            $document->forceFill(['drylog_material_id' => $material->id, 'drylog_qty' => 0])->save();
        }

        $document->lock();
    }
}
