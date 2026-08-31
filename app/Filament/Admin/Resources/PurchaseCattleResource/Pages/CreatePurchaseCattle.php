<?php

namespace App\Filament\Admin\Resources\PurchaseCattleResource\Pages;

use App\Filament\Admin\Resources\PurchaseCattleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreatePurchaseCattle extends CreateRecord
{
    protected static string $resource = PurchaseCattleResource::class;

    protected function beforeValidate(): void
    {
        $items = $this->data['items'] ?? [];
        foreach ($items as $key => $item) {
            if (empty($item['cattle_class_id'])) {
                unset($items[$key]);
            }
        }
        $this->data['items'] = $items;
    }

    /**
     * Pembuatan dibungkus transaksi supaya kunci nomor dokumen bertahan.
     *
     * `PurchaseCattle::generateDocumentNumber()` memakai `lockForUpdate()`.
     * Sebuah kunci baris hanya berlaku selama transaksi yang membukanya --
     * dan dulu transaksinya berada di dalam `creating()`, sehingga ia commit
     * sebelum INSERT dijalankan. Persis pada celah antara membaca nomor
     * terakhir dan menyimpan yang baru, kuncinya sudah lepas.
     *
     * Dengan transaksinya di sini, kunci bertahan sampai barisnya benar-benar
     * tersimpan.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(fn (): Model => parent::handleRecordCreation($data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
