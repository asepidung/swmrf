<?php

namespace App\Filament\Admin\Resources\SalesReturnPlanResource\Pages;

use App\Filament\Admin\Resources\SalesReturnPlanResource;
use App\Models\SalesReturnPlan;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Header + item dalam SATU langkah (issue #478) -- sebelumnya Create
 * hanya menyimpan header lalu mengalihkan ke halaman item terpisah.
 * Item disimpan manual (bukan `->relationship()`), dalam SATU transaksi
 * dengan header-nya: kalau ada baris yang ditolak validasi server (mis.
 * klaim melebihi yang terkirim di DO -- `SalesReturnPlanItem::booted()`),
 * seluruh plan batal, bukan header tersimpan sendirian tanpa item.
 */
class CreateSalesReturnPlan extends CreateRecord
{
    protected static string $resource = SalesReturnPlanResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $itemsData = $data['items'] ?? [];
        unset($data['items']);

        try {
            return DB::transaction(function () use ($data, $itemsData): SalesReturnPlan {
                $plan = SalesReturnPlan::create($data);

                foreach ($itemsData as $item) {
                    $plan->items()->create([
                        'product_id' => $item['product_id'],
                        'claimed_weight' => $item['claimed_weight'],
                        'claimed_qty_pcs' => $item['claimed_qty_pcs'] ?? null,
                        'note' => $item['note'] ?? null,
                    ]);
                }

                return $plan;
            });
        } catch (\Exception $e) {
            report($e);
            Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

            throw new Halt();
        }
    }

    /** Langsung ke Edit -- langkah wajar berikutnya adalah meninjau lalu Submit. */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
