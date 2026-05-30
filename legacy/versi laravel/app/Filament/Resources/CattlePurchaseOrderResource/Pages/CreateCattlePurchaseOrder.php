<?php

namespace App\Filament\Resources\CattlePurchaseOrderResource\Pages;

use App\Filament\Resources\CattlePurchaseOrderResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\CattlePurchaseOrder;
use Illuminate\Support\Facades\Auth;

class CreateCattlePurchaseOrder extends CreateRecord
{
    protected static string $resource = CattlePurchaseOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 1. Catat siapa yang bikin (Anti-Merah Intelephense)
        $data['created_by'] = (int) Auth::id();

        $currentYear2Digit = date('y');
        $currentYear4Digit = date('Y');

        // 2. KUNCI UTAMA: Tambahin withTrashed() biar yang dihapus tetep masuk hitungan
        $countAllRecords = CattlePurchaseOrder::withTrashed()
            ->whereYear('created_at', $currentYear4Digit)
            ->count();

        // Kalau sudah ada 1 data (meskipun dihapus), $urut bakal jadi 2
        $urut = $countAllRecords + 1;

        // 3. Generate Nomor PO (Contoh: SWM-PC#26002)
        $data['po_number'] = 'SWM-PC#' . $currentYear2Digit . str_pad((string)$urut, 3, '0', STR_PAD_LEFT);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
