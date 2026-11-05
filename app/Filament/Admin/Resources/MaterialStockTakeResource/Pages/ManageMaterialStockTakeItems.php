<?php

namespace App\Filament\Admin\Resources\MaterialStockTakeResource\Pages;

use App\Filament\Admin\Resources\MaterialStockTakeResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Notifications\Notification;

class ManageMaterialStockTakeItems extends ManageRelatedRecords
{
    /**
     * Halaman ini TIDAK boleh terbuka lewat alamatnya saja.
     *
     * Tidak ada `MaterialStockTakeItemPolicy`, dan proyek ini tidak punya
     * `Gate::before` -- jadi `authorize('viewAny', MaterialStockTakeItem::class)`
     * bawaan `ManageRelatedRecords::canAccess()` jatuh ke `Response::allow()`.
     * Tombol "Input Stock" di daftar memang disembunyikan dari yang tidak
     * berhak, tapi alamatnya sendiri tidak tertutup -- siapa pun yang bisa
     * masuk panel admin bisa mengetik URL-nya langsung dan mengubah hitungan
     * fisik sebelum opname diselesaikan.
     *
     * Susulan 17 September 2026: sebelumnya mensyaratkan
     * `view_material_stock_takes`, padahal halaman inilah tempat perubahan
     * stok sungguhan terjadi (kolom `physical_qty` menulis langsung ke
     * `MaterialStockTakeItem`). Keputusan yang sama dengan Tally: yang
     * MENGUBAH stok butuh izin `edit_...`, bukan cuma `view_...`.
     */
    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->isProgrammer()
            || (auth()->user()?->hasPermission('edit_material_stock_takes') ?? false);
    }

    protected static string $resource = MaterialStockTakeResource::class;

    protected static string $relationship = 'items';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    public function getTitle(): string
    {
        // Kuncinya utuh, dokumennya lewat penampung -- bukan kunci yang
        // berakhir " - " lalu disambung teks.
        return __('Input Material Counts for :document', [
            'document' => $this->getOwnerRecord()->document_number,
        ]);
    }

    public function getSubheading(): ?string
    {
        $dokumen = $this->getOwnerRecord()->fresh();

        // Alasan peninjau meminta hitung ulang, terlihat selama masih IN_PROGRESS dan belum dikirim ulang.
        if ($dokumen && $dokumen->isCountable() && filled($dokumen->recount_note)) {
            return __('Recount requested').': '.$dokumen->recount_note;
        }

        return null;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        $actions[] = Actions\Action::make('back')
            ->label(__('Back to List'))
            ->color('gray')
            ->url($this->getResource()::getUrl('index'));

        // Kirim untuk ditinjau dari sini, tempat penghitung selesai bekerja (Owner, 5 November 2026). Sebelumnya tombol
        // ini hanya ada di halaman Ubah yang tidak punya tautan dari mana pun.
        if ($this->getOwnerRecord()->isCountable()) {
            $actions[] = Actions\Action::make('submit_for_review')
                ->label(__('Submit for Review'))
                ->color('info')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->modalHeading(__('Submit this stock count for review?'))
                ->modalDescription(__('Once submitted, the counts can no longer be edited and the variance is shown for review.'))
                ->action(function () {
                    if (! $this->getOwnerRecord()->submitForReview()) {
                        Notification::make()->title(__('This stock count has already been finished'))->warning()->send();

                        return;
                    }

                    Notification::make()->title(__('Sent for review.'))->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                });
        }

        // Keputusan Owner, 17 September 2026: Complete/Finish HANYA dari
        // halaman REVIEW (EditMaterialStockTake) -- tombol di halaman
        // input ini (yang sebelumnya menyelesaikan langsung dari
        // DRAFT/IN_PROGRESS, sama sekali melewati tahap peninjauan)
        // dihapus. Menyelesaikan opname sekarang wajib singgah di
        // REVIEW lebih dulu, terlepas dari halaman mana pengguna mulai.

        return $actions;
    }

    /** Bisa melihat selisih dan meminta hitung ulang di tahap REVIEW. */
    private function bisaMeninjau(): bool
    {
        return auth()->user()?->isProgrammer()
            || (auth()->user()?->hasPermission('finish_material_stock_takes') ?? false);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public function getFooter(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.pages.manage-stock-take-items-footer');
    }

    public function table(Table $table): Table
    {
        $doc = $this->getOwnerRecord();
        $isCompleted = $doc->status === \App\Models\MaterialStockTake::STATUS_COMPLETED;
        $isReview = $doc->status === \App\Models\MaterialStockTake::STATUS_REVIEW;
        $bisaMeninjau = $this->bisaMeninjau();

        // Baris boleh diedit kalau dokumennya masih dalam jendela hitung
        // (DRAFT/IN_PROGRESS) DAN baris itu sendiri belum dikunci lewat
        // Submit for Review. "Minta Hitung Ulang" membuka is_locked
        // kembali untuk baris tertentu tanpa mengunci baris lain --
        // itulah kenapa ini per BARIS, bukan cuma status dokumen.
        $editable = fn (?\App\Models\MaterialStockTakeItem $record): bool => $record && $doc->isCountable() && ! $record->is_locked;

        return $table
            ->recordTitleAttribute('id')
            ->paginated(false)
            // Urut abjad menurut nama barang (permintaan Owner, 5 November 2026), bukan urutan masuk.
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy(
                \App\Models\Material::select('name')->whereColumn('materials.id', 'material_stock_take_items.material_id')
            ))
            ->columns([
                Tables\Columns\TextColumn::make('material.code')
                    ->label(__('Item Code'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('material.name')
                    ->label(__('Item Name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('material.unit.name')
                    ->label(__('Unit')),
                Tables\Columns\TextColumn::make('system_qty')
                    ->label(__('System Qty'))
                    ->numeric(decimalPlaces: 0, decimalSeparator: ',', thousandsSeparator: '.')
                    ->visible($isCompleted),

                // Only show text input if the row is still editable
                Tables\Columns\TextInputColumn::make('physical_qty')
                    ->label(__('Physical Qty'))
                    ->type('text')
                    // Terkunci atau lewat tahap hitung: isian tetap tampil tapi tidak bisa diubah. JANGAN memakai
                    // ->visible() dengan $record: visibilitas kolom dievaluasi sekali untuk seluruh tabel, tanpa baris,
                    // sehingga isiannya tidak pernah muncul (ditemukan 5 November 2026).
                    ->disabled(fn (?\App\Models\MaterialStockTakeItem $record): bool => ! $editable($record))
                    // Hitungan material selalu BILANGAN BULAT.
                    //
                    // Keputusan Owner: "material itu gak ada qty koma-komaan".
                    //
                    // Penguraian lamanya membuang setiap titik sebagai
                    // pemisah ribuan, sehingga mengetik "12.5" diam-diam
                    // menjadi 125 -- sepuluh kali lipat, tanpa satu pun
                    // gejala, di isian yang langsung memotong atau menambah
                    // stok. Dan di layar pindai daging titik justru pemisah
                    // desimal, jadi dua layar opname di aplikasi yang sama
                    // membaca angka dengan cara yang berlawanan.
                    //
                    // Sekarang yang memuat pemisah desimal DITOLAK, bukan
                    // ditebak.
                    ->rules(['nullable', 'integer', 'min:0'])
                    // Tersimpan saat kolom ditinggalkan (Tab/Enter/klik di luar), bawaan Filament. Autosave saat mengetik
                    // PERNAH dicoba (5 November 2026) tapi kolom terkunci selama menyimpan sehingga ketikan hilang-hilangan.
                    // Sebagai gantinya, menutup tab atau me-refresh saat masih ada ketikan yang belum disimpan memunculkan
                    // peringatan dari browser, jadi angkanya tidak hilang diam-diam.
                    ->extraInputAttributes([
                        'x-on:input' => 'window.stokBelumTersimpan = true',
                        'x-on:change.capture' => 'window.stokBelumTersimpan = false',
                        'x-on:beforeunload.window' => "if (window.stokBelumTersimpan) { \$event.preventDefault(); \$event.returnValue = ''; }",
                    ])
                    ->updateStateUsing(function (\App\Models\MaterialStockTakeItem $record, $state) use ($doc) {
                        // Lapis kedua: `canAccess()` menjaga PINTU halaman,
                        // tapi kolom tabel yang bisa diedit inline adalah
                        // method Livewire yang bisa dipanggil langsung.
                        if (! (auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('edit_material_stock_takes') ?? false))) {
                            Notification::make()
                                ->title(__('You do not have permission to do this.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        // Baris terkunci (sudah dikirim untuk ditinjau, dan
                        // tidak dipilih untuk hitung ulang) tidak boleh
                        // ditulis lewat jalur ini juga -- `visible()` di
                        // atas cuma menyembunyikan kolomnya, method ini
                        // sendiri tetap bisa dipanggil langsung.
                        if ($record->is_locked || ! $doc->fresh()?->isCountable()) {
                            Notification::make()
                                ->title(__('This item is locked and can no longer be edited.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $bersih = trim((string) $state);

                        if ($bersih === '') {
                            $record->physical_qty = null;
                            $record->difference_qty = null;
                            $record->save();

                            return;
                        }

                        // Pemisah ribuan boleh diketik, desimal tidak.
                        $angka = str_replace('.', '', $bersih);

                        if (! preg_match('/^\d+$/', $angka)) {
                            Notification::make()
                                ->title(__('Enter a whole number'))
                                ->body(__('Material is counted in whole units, so :value cannot be read.', ['value' => $bersih]))
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->physical_qty = (int) $angka;
                        $record->difference_qty = $record->physical_qty - $record->system_qty;
                        $record->save();
                    }),
                
                // Selisih (dan status Over/Short/Sesuai turunannya) HANYA
                // terlihat sesudah COMPLETED (riwayat permanen, buat siapa
                // saja yang boleh melihat halaman ini) atau selama REVIEW
                // TAPI hanya buat pemegang izin finish_material_stock_takes
                // -- keputusan Owner, 17 September 2026. Aturannya satu
                // rumah di `MaterialStockTakeItem`.
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Variance Status'))
                    ->visible($isCompleted || ($isReview && $bisaMeninjau))
                    ->getStateUsing(fn (\App\Models\MaterialStockTakeItem $record): string => $record->varianceLabel())
                    ->badge()
                    ->color(fn ($state, \App\Models\MaterialStockTakeItem $record): string => $record->varianceColor()),

                Tables\Columns\TextColumn::make('difference_qty')
                    ->label(__('Difference Qty'))
                    ->numeric(decimalPlaces: 0, decimalSeparator: ',', thousandsSeparator: '.')
                    ->visible($isCompleted || ($isReview && $bisaMeninjau))
                    ->color(fn ($state) => $state > 0 ? 'info' : ($state < 0 ? 'danger' : 'success')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('request_recount')
                        ->label(__('Request Recount'))
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->form([\Filament\Forms\Components\Textarea::make('note')->label(__('Reason for recount'))->required()->maxLength(500)->rows(3)])
                        ->modalHeading(__('Request a recount for the selected items?'))
                        ->modalDescription(__('The stock count returns to In Progress for these items only. Their previous counts are kept as history, and the input fields start empty again.'))
                        // Sama seperti Complete Opname: yang membuka
                        // kembali hitungan yang sudah ditinjau adalah
                        // keputusan peninjau, jadi izin yang sama.
                        ->visible(fn (): bool => $isReview && $bisaMeninjau)
                        ->action(function (\Illuminate\Support\Collection $records, array $data) {
                            // Lapis kedua: visible() menjaga TOMBOLNYA,
                            // method ini sendiri dipanggil langsung lewat
                            // Livewire terlepas dari itu.
                            if (! $this->bisaMeninjau()) {
                                Notification::make()->title(__('You do not have permission to do this.'))->danger()->send();

                                return;
                            }

                            if (! $this->getOwnerRecord()->requestRecount($records->pluck('id')->all(), $data['note'] ?? null)) {
                                Notification::make()
                                    ->title(__('This stock count has already been finished'))
                                    ->warning()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title(__('Recount requested for the selected items.'))
                                ->success()
                                ->send();

                            $this->redirect($this->getResource()::getUrl('items', ['record' => $this->getOwnerRecord()]));
                        }),
                ]),
            ]);
    }
}
