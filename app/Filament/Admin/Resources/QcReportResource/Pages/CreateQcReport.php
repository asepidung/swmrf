<?php

namespace App\Filament\Admin\Resources\QcReportResource\Pages;

use App\Filament\Admin\Resources\QcReportResource;
use App\Models\QcReport;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Menulis laporan QC untuk sebuah dokumen.
 *
 * Halaman ini SELALU dibuka dari dokumennya -- dari daftar tugas di Dashboard
 * atau dari tombol di halaman dokumen itu -- dan alamatnya menyebutkan
 * dokumen mana yang didampingi.
 */
class CreateQcReport extends CreateRecord
{
    protected static string $resource = QcReportResource::class;

    private ?Model $dokumen = null;

    public function mount(): void
    {
        $this->dokumen = $this->dokumenDariAlamat();

        if (! $this->dokumen) {
            Notification::make()
                ->title(__('This QC report has no document to accompany.'))
                ->body(__('Open it from the document that needs inspecting.'))
                ->warning()
                ->send();

            $this->redirect($this->getResource()::getUrl('index'));

            return;
        }

        parent::mount();

        $this->form->fill([
            'reportable_type' => $this->dokumen::class,
            'reportable_id' => $this->dokumen->getKey(),
            'occurred_at' => now(),
        ]);
    }

    /**
     * Dokumen yang disebut alamat, ATAU `null` kalau tidak sah.
     *
     * Jenisnya diterjemahkan lewat daftar di `QcReport::DOKUMEN`, tidak
     * pernah diambil mentah dari URL. Alamat yang boleh menyebut nama kelas
     * apa pun akan membuat laporan QC bisa ditempelkan ke model mana saja di
     * aplikasi ini -- pengguna, pembayaran, apa pun -- dan tidak ada satu pun
     * gejala yang menunjukkan itu terjadi.
     */
    private function dokumenDariAlamat(): ?Model
    {
        $kelas = QcReport::kelasUntuk(request()->query('dokumen'));

        if (! $kelas) {
            return null;
        }

        $id = request()->query('id');

        if (! is_string($id) && ! is_int($id)) {
            return null;
        }

        return $kelas::find($id);
    }

    /**
     * Jenis dan nomor dokumennya tidak pernah dipercaya dari form.
     *
     * Keduanya sudah ditentukan saat halamannya dibuka. Membacanya kembali
     * dari state form berarti mempercayai nilai yang bisa diganti dari
     * peramban -- dan laporan mutu yang bisa dipindahkan ke dokumen lain
     * setelah ditulis bukan laporan.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $dokumen = $this->dokumen ?? $this->dokumenDariAlamat();

        abort_unless((bool) $dokumen, 404);

        $data['reportable_type'] = $dokumen::class;
        $data['reportable_id'] = $dokumen->getKey();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getTitle(): string
    {
        if (! $this->dokumen) {
            return __('QC Report');
        }

        return __('QC report for :type :number', [
            'type' => __(class_basename($this->dokumen::class)),
            'number' => $this->dokumen->document_number
                ?? $this->dokumen->carcass_number
                ?? '#'.$this->dokumen->getKey(),
        ]);
    }
}
