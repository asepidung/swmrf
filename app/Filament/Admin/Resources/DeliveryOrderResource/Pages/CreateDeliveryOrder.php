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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function mount(): void
    {
        $tallyId = request()->query('tally_id');

        if (!$tallyId || !Tally::where('id', $tallyId)->exists()) {
            $this->redirect($this->getResource()::getUrl('draft'));
            return;
        }

        parent::mount();
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
            Tally::whereKey($data['tally_id'])->lockForUpdate()->first();

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
