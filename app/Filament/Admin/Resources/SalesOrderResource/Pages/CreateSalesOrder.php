<?php

namespace App\Filament\Admin\Resources\SalesOrderResource\Pages;

use App\Filament\Admin\Resources\SalesOrderResource;
use App\Models\CustomerProductSpec;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesOrder extends CreateRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected array $itemsData = [];

    /** K-05: DP yang diisi harus punya rekening/kas dan pelanggan bergrup, supaya bisa dicatat sebagai uang masuk. */
    protected function beforeCreate(): void
    {
        $raw = $this->form->getRawState();
        $dp = (float) str_replace('.', '', (string) ($raw['down_payment'] ?? 0));
        $grup = \App\Models\Customer::whereKey($raw['customer_id'] ?? null)->value('customer_group_id');

        if ($dp > 0 && (blank($raw['down_payment_bank_account_id'] ?? null) || ! $grup)) {
            \Filament\Notifications\Notification::make()
                ->title(__('The down payment needs a bank/cash account and a customer with a group.'))
                ->danger()->send();

            $this->halt();
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->itemsData = $data['items'] ?? [];
        unset($data['items']);
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        // K-05: DP dicatat sebagai pembayaran masuk buku kas.
        \App\Support\CustomerDownPayment::record($record, (int) ($this->form->getRawState()['down_payment_bank_account_id'] ?? 0));
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

        // Sales Order baru lahir dengan status 'waiting', dan itu persis
        // keadaan yang ditunggu halaman Draft Tally. Jadi begitu SO tersimpan,
        // pekerjaannya memang sudah menganggur di meja orang lain.
        //
        // Sengaja hanya saat DIBUAT, bukan saat disunting: menyunting SO yang
        // sama beberapa kali tidak melahirkan pekerjaan baru, dan mengirim
        // notifikasi tiap kali hanya membuat orang berhenti membacanya.
        \App\Support\TaskNotifier::notifyPermissionHolders(
            'create_tallies',
            __('New Sales Order'),
            // Sengaja pendek dan tanpa nomor dokumen. Di layar HP judul dan
            // isi sama-sama terpotong bila panjang, dan nomornya toh langsung
            // terlihat begitu notifikasinya dibuka.
            __('Ready to be tallied.'),
            \App\Filament\Admin\Resources\TallyResource::getUrl('draft'),
            'sales-order-'.$record->id,
            auth()->id(),
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // Tanpa ini, isian wajib yang masih kosong memunculkan gelembung bawaan BROWSER ("Please fill out this
    // field.") dalam bahasa browser, menempel di kolom yang labelnya cuma placeholder, dan form tidak pernah
    // sampai ke server. Dengan formnovalidate, validasi dikerjakan Filament: pesannya inline, diterjemahkan,
    // dan menyebut semua kolom yang kurang sekaligus. Audit UI/UX 6 November 2026 (#808).
    protected function getCreateFormAction(): Actions\Action
    {
        return parent::getCreateFormAction()->extraAttributes(['formnovalidate' => true]);
    }
}
