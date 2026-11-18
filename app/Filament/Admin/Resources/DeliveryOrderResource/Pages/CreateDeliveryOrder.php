<?php

namespace App\Filament\Admin\Resources\DeliveryOrderResource\Pages;

use App\Filament\Admin\Resources\DeliveryOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\DeliveryOrder;
use App\Models\Tally;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateDeliveryOrder extends CreateRecord
{
    protected static string $resource = DeliveryOrderResource::class;

    /** Tally yang halaman ini dibuka untuknya; tidak bisa diubah dari browser (K-04). */
    #[\Livewire\Attributes\Locked]
    public ?int $tallyId = null;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function mount(): void
    {
        $tallyId = request()->query('tally_id');

        // Hanya tally yang sudah DIKUNCI (selesai dipindai dan disetujui) yang boleh dibuatkan surat jalan.
        if (!$tallyId || !Tally::where('id', $tallyId)->where('status', Tally::STATUS_LOCKED)->exists()) {
            $this->redirect($this->getResource()::getUrl('draft'));
            return;
        }

        $this->tallyId = (int) $tallyId;

        parent::mount();
    }

    /** Tally, pelanggan, dan SO surat jalan berasal dari tally yang dikunci, bukan dari kiriman browser (K-04). */
    protected function beforeValidate(): void
    {
        $tally = Tally::with('salesOrder')->find($this->tallyId);

        if ($tally) {
            $this->data['tally_id'] = $tally->id;
            $this->data['sales_order_id'] = $tally->sales_order_id;
            $this->data['customer_id'] = $tally->salesOrder?->customer_id;
        }
    }

    protected function afterCreate(): void
    {
        // Baris surat jalan = isi tally, bukan kiriman form.
        $this->record->syncItemsFromTally();
    }

    /**
     * Tidak ada unique index di `tally_id` -- SENGAJA. Kunci unique akan
     * menghalangi Tally yang DO-nya sudah dihapus (soft delete) untuk
     * dibuatkan DO baru, padahal itu alur pemulihan yang sah.
     *
     * Penjagaannya di sini: kunci baris Tally-nya dulu, baru periksa apakah
     * sudah ada DO untuknya. Dua permintaan submit bersamaan (klik ganda)
     * untuk Tally yang sama tidak bisa lagi sama-sama lolos pemeriksaan lalu
     * sama-sama membuat DO -- yang kedua menunggu baris Tally terbuka,
     * lalu melihat DO yang pertama sudah ada.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $tally = Tally::whereKey($data['tally_id'])->lockForUpdate()->first();

            if (! $tally || $tally->status !== Tally::STATUS_LOCKED) {
                throw ValidationException::withMessages([
                    'data.tally_id' => __('This Tally is not locked, so it cannot get a Delivery Order yet.'),
                ]);
            }

            if (DeliveryOrder::where('tally_id', $data['tally_id'])->exists()) {
                throw ValidationException::withMessages([
                    'data.tally_id' => __('This Tally already has a Delivery Order.'),
                ]);
            }

            return static::getModel()::create($data);
        });
    }

    protected function getCreateAnotherFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateAnotherFormAction()->hidden();
    }
}
