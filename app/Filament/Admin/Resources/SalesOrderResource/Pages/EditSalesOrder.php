<?php

namespace App\Filament\Admin\Resources\SalesOrderResource\Pages;

use App\Filament\Admin\Resources\SalesOrderResource;
use App\Models\SalesOrder;
use App\Support\MasterDataDeletion;
use App\Models\CustomerProductSpec;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalesOrder extends EditRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label(__('Print'))
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn(): string => route('print.salesorder', $this->record))
                ->openUrlInNewTab()
                // `!` mengikat lebih dulu daripada `===`, jadi bentuk lamanya
                // `(!$this->record->status) === 'cancelled'` -- selalu
                // `false === 'cancelled'`, selalu false. Tombol Print TIDAK
                // PERNAH tampil, status apa pun.
                ->visible(fn(): bool => $this->record->status !== SalesOrder::STATUS_CANCELLED),
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
            // K-05: membatalkan DP membalik pembayarannya di buku kas; tidak bisa bila DP sudah dipakai invoice.
            Actions\Action::make('cancel_down_payment')
                ->label(__('Cancel down payment'))
                ->color('danger')
                ->requiresConfirmation()
                ->authorize(fn (): bool => auth()->user()?->hasPermission('edit_sales_orders') ?? false)
                ->form([\Filament\Forms\Components\Textarea::make('reason')->label(__('Reason'))->required()])
                ->visible(fn (): bool => (float) $this->record->down_payment > 0 && ! in_array($this->record->status, SalesOrder::STATUS_LOCKED_FOR_EDIT, true))
                ->action(function (array $data): void {
                    $so = $this->getRecord();

                    if (\App\Models\Invoice::where('sales_order_id', $so->id)->where('down_payment', '>', 0)->exists()) {
                        \Filament\Notifications\Notification::make()->title(__('The down payment is already used by an invoice and cannot be cancelled.'))->danger()->send();

                        return;
                    }

                    \Illuminate\Support\Facades\DB::transaction(function () use ($so, $data): void {
                        \App\Support\CustomerDownPayment::activePayment($so)?->cancel($data['reason']);
                        $so->update(['down_payment' => 0]);
                    });

                    $this->refreshFormData(['down_payment']);
                    \Filament\Notifications\Notification::make()->title(__('Down payment cancelled'))->success()->send();
                }),
            Actions\DeleteAction::make()
                ->hidden(fn(): bool => in_array($this->record->status, SalesOrder::STATUS_LOCKED_FOR_EDIT, true)),
            // tallies.sales_order_id (unique, RESTRICT) dan
            // invoices.sales_order_id (RESTRICT) -- datanya aman, tapi
            // tanpa ini force-delete SO yang masih punya Tally/Invoice
            // menampilkan galat SQL mentah.
            Actions\ForceDeleteAction::make()
                ->hidden(fn(): bool => in_array($this->record->status, SalesOrder::STATUS_LOCKED_FOR_EDIT, true))
                ->action(function () {
                    $record = $this->getRecord();

                    if (MasterDataDeletion::attempt(fn () => $record->forceDelete(), __('Sales Order').' '.$record->so_number)) {
                        $this->redirect($this->getResource()::getUrl('index'));
                    }
                }),
        ];
    }

    protected function getFormActions(): array
    {
        if (in_array($this->record->status, SalesOrder::STATUS_LOCKED_FOR_EDIT, true)) {
            return [];
        }
        return [
            $this->getSaveFormAction(),
        ];
    }

    // Lihat alasan di CreateSalesOrder::getCreateFormAction() (#808).
    protected function getSaveFormAction(): Actions\Action
    {
        return parent::getSaveFormAction()->extraAttributes(['formnovalidate' => true]);
    }

    protected array $itemsData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $items = [];
        foreach ($this->record->items as $item) {
            $items['item_' . \Illuminate\Support\Str::random(12)] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'weight' => number_format($item->weight, 0, '', '.'),
                'price' => number_format($item->price, 0, '', '.'),
                'discount' => number_format($item->discount, 0, '', '.'),
                'note' => $item->note,
                'spec' => CustomerProductSpec::encode($item->spec),
            ];
        }
        $data['items'] = $items;
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->itemsData = $data['items'] ?? [];
        unset($data['items']);

        // SO yang tally-nya sedang dipindai: kolom yang dinonaktifkan di layar tidak boleh berubah lewat state yang
        // dikirim di luar layar (audit pra-rilis, SO-04). Status dibaca dari basis data, bukan dari kiriman.
        if (SalesOrder::whereKey($this->getRecord()->getKey())->value('status') === SalesOrder::STATUS_PROCESSING) {
            unset($data['customer_id'], $data['delivery_date'], $data['shipping_address'], $data['down_payment']);
        }

        // K-05: DP yang sudah terisi (tercatat, atau DP lama) tidak bisa diubah lewat Simpan; hanya lewat Batalkan DP.
        if ((float) SalesOrder::whereKey($this->getRecord()->getKey())->value('down_payment') > 0) {
            unset($data['down_payment']);
        }

        return $data;
    }

    /**
     * Tombol Save disembunyikan untuk SO terkunci, tetapi method `save` tetap bisa dipanggil langsung (dan dari tab
     * yang dibuka sebelum SO dikunci). Tanpa ini `afterSave()` menghapus semua baris SO dan tidak membuat penggantinya.
     */
    protected function beforeSave(): void
    {
        $raw = $this->form->getRawState();
        $dpBaru = (float) str_replace('.', '', (string) ($raw['down_payment'] ?? 0));
        $dpAda = (float) SalesOrder::whereKey($this->getRecord()->getKey())->value('down_payment');
        $grup = \App\Models\Customer::whereKey($raw['customer_id'] ?? $this->getRecord()->customer_id)->value('customer_group_id');

        if ($dpAda <= 0 && $dpBaru > 0 && (blank($raw['down_payment_bank_account_id'] ?? null) || ! $grup)) {
            \Filament\Notifications\Notification::make()
                ->title(__('The down payment needs a bank/cash account and a customer with a group.'))
                ->danger()->send();

            $this->halt();
        }

        $status = SalesOrder::whereKey($this->getRecord()->getKey())->value('status');

        if (in_array($status, SalesOrder::STATUS_LOCKED_FOR_EDIT, true)) {
            \Filament\Notifications\Notification::make()
                ->title(__('This sales order is locked, so it cannot be saved.'))
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        // K-05: DP yang baru diisi di SO ini dicatat sebagai pembayaran masuk buku kas.
        \App\Support\CustomerDownPayment::record($record->fresh(), (int) ($this->form->getRawState()['down_payment_bank_account_id'] ?? 0));
        $record->items()->delete();
        
        foreach ($this->itemsData as $item) {
            $record->items()->create([
                'product_id' => $item['product_id'],
                // Berat dan harga memang berpemisah ribuan -- titiknya
                // dibuang di sini karena JavaScript di form yang memasangnya.
                //
                // Diskon TIDAK. Form sengaja tidak memformat kolom itu, jadi
                // titik di sana hanya mungkin berarti koma desimal, dan
                // membuangnya bukan membulatkan melainkan mengubah artinya:
                // 2,5% menjadi 25%, 12,75% menjadi 1275%. Validasi tidak
                // menangkapnya karena perusakannya terjadi SESUDAH validasi.
                //
                // Diskon kini persen bulat, jadi dibaca apa adanya.
                'weight' => (int) str_replace('.', '', $item['weight'] ?? 0),
                'price' => (int) str_replace('.', '', $item['price'] ?? 0),
                'discount' => (int) ($item['discount'] ?? 0),
                'note' => $item['note'] ?? '',
                // Salinan spek customer (#579), apa adanya dari form.
                'spec' => CustomerProductSpec::decode($item['spec'] ?? null),
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
