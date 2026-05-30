<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;

    /**
     * Setelah berhasil membuat data (Create), 
     * arahkan user kembali ke halaman daftar (Index).
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Opsional: Mengganti notifikasi sukses (bawaan Filament sudah ada)
     * Kalau mau custom tulisan notifikasinya, bisa pakai fungsi ini.
     */
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Data Supplier Berhasil Disimpan!';
    }
}
