<?php

namespace App\Filament\Admin\Resources\CattleReceivingResource\Pages;

use App\Filament\Admin\Resources\CattleReceivingResource;
use App\Models\PurchaseCattle;
use App\Filament\Admin\Resources\CattleReceivingResource\Concerns\SavesUniqueEartags;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateCattleReceiving extends CreateRecord
{
    use SavesUniqueEartags;

    protected static string $resource = CattleReceivingResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveGuardingEartags(fn (): Model => parent::handleRecordCreation($data));
    }

    protected function afterCreate(): void
    {
        $this->issuePayable();
    }

    /**
     * Terbitkan utangnya, dan JANGAN diam bila tidak bisa.
     *
     * Utang yang gagal terbit tanpa pemberitahuan adalah kegagalan yang
     * paling mahal di modul ini: dokumennya tersimpan, layarnya terlihat
     * normal, dan tagihan supplier baru muncul berminggu-minggu kemudian
     * tanpa ada yang tahu asal selisihnya.
     */
    protected function issuePayable(): void
    {
        $receiving = $this->getRecord()->fresh(['items.cattleClass', 'purchaseCattle.items', 'supplier']);

        if ($receiving->syncPayable()) {
            return;
        }

        $unpriced = \App\Models\Payable::unpricedCattleClasses($receiving);

        Notification::make()
            ->warning()
            ->title(__('Payable not issued yet'))
            ->body($unpriced === []
                ? __('This receiving has no cattle recorded, so no payable was created.')
                : __('These cattle classes have no price on the PO, so the payable was not created: :classes. Add the price on the PO, then save this document again.', [
                    'classes' => implode(', ', $unpriced),
                ]))
            ->persistent()
            ->send();
    }

    public function mount(): void
    {
        $poId = request()->query('po_id');
        if (!$poId) {
            $this->redirect($this->getResource()::getUrl('index'));
            return;
        }

        parent::mount();

        $po = PurchaseCattle::with(['items', 'supplier'])->find($poId);

        if ($po) {
            $this->form->fill([
                'purchase_cattle_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'po_number_display' => $po->document_number,
                'supplier_name_display' => $po->supplier->name,
                'receive_date' => now()->format('Y-m-d'),
            ]);
        }
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
