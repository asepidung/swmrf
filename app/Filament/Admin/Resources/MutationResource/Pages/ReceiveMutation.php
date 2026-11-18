<?php

namespace App\Filament\Admin\Resources\MutationResource\Pages;

use App\Filament\Admin\Resources\MutationResource;
use App\Models\Mutation;
use App\Models\MutationItem;
use App\Models\BeefStock;
use Filament\Resources\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Filament\Support\Enums\MaxWidth;

class ReceiveMutation extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string $resource = MutationResource::class;

    protected static string $view = 'filament.admin.resources.mutation-resource.pages.receive-mutation';

    public Mutation $record;
    public ?string $barcode = '';

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    public function getHeading(): string
    {
        return __('Receiving mutation :number', ['number' => $this->record->mutation_number]);
    }

    public function mount(Mutation $record): void
    {
        $this->record = $record;

        if ($this->record->status !== 'SENT') {
            Notification::make('scan-toast')->title(__('This mutation has not been sent yet, or is already finished.'))->warning()->send();
            $this->redirect(MutationResource::getUrl('view', ['record' => $this->record->id]));
            return;
        }
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('barcode')
                ->label(__('Scan Barcode'))
                ->placeholder(__('Put the cursor here and start scanning the goods received...'))
                ->required()
                ->autofocus()
                ->extraInputAttributes([
                    'x-on:keydown.enter' => '$wire.addBarcode()',
                    'x-ref' => 'barcodeInput',
                    'class' => 'text-xl font-bold tracking-widest text-center',
                ]),
        ];
    }

    public function addBarcode(): void
    {
        $barcode = trim($this->barcode);
        $this->barcode = '';

        if (empty($barcode)) return;

        $item = MutationItem::where('mutation_id', $this->record->id)
            ->where('barcode', $barcode)
            ->first();

        if (!$item) {
            Notification::make('scan-toast')->title(__('This barcode is not on this mutation.'))->danger()->send();
            $this->dispatch('focus-barcode');
            return;
        }

        if ($item->is_received) {
            Notification::make('scan-toast')->title(__('This barcode has already been received.'))->warning()->send();
            $this->dispatch('focus-barcode');
            return;
        }

        if (! $this->selamaMasihDikirim(fn () => $item->update(['is_received' => true]))) {
            return;
        }

        Notification::make('scan-toast')->title(__('Barcode scanned'))->success()->send();
        $this->dispatch('focus-barcode');
    }

    /**
     * Menjalankan perubahan HANYA selama mutasinya masih SENT, dengan baris
     * Mutation dikunci dan statusnya dibaca ULANG dari basis data.
     *
     * `mount()` memeriksa status sekali saat halaman dibuka. Tab yang masih
     * terbuka sesudah mutasinya diselesaikan di sesi lain tetap bisa
     * menekan tombol di sini -- dan untuk `finish` itu berarti stok gudang
     * tujuan dibuat DUA KALI (audit pra-rilis 14 November 2026, S-01/S-03).
     * Pola yang sama dengan unscan di `ScanMutation`.
     */
    private function selamaMasihDikirim(callable $ubah): bool
    {
        $boleh = DB::transaction(function () use ($ubah): bool {
            $mutation = Mutation::whereKey($this->record->id)->lockForUpdate()->first();

            if (! $mutation || $mutation->status !== 'SENT') {
                return false;
            }

            $ubah($mutation);

            return true;
        });

        if (! $boleh) {
            Notification::make('scan-toast')
                ->title(__('This mutation has not been sent yet, or is already finished.'))
                ->warning()
                ->send();
        }

        return $boleh;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(MutationItem::query()->where('mutation_id', $this->record->id)->where('is_received', true))
            ->columns([
                Tables\Columns\TextColumn::make('barcode')->label(__('Barcode')),
                Tables\Columns\TextColumn::make('product.name')->label(__('Product')),
                Tables\Columns\TextColumn::make('weight')->label(__('Qty'))->numeric(2),
                Tables\Columns\TextColumn::make('qty_pcs')->label(__('Pcs')),
                Tables\Columns\TextColumn::make('grade.name')->formatStateUsing(fn ($state) => \App\Models\Grade::initial($state))->label(__('Grade')),
                Tables\Columns\TextColumn::make('ph_level')->label(__('pH'))->numeric(1),
                Tables\Columns\TextColumn::make('pack_date')->label(__('POD'))->date('d M Y'),
                Tables\Columns\TextColumn::make('origin')->label(__('Origin')),
            ])
            ->actions([
                Tables\Actions\Action::make('cancel_receive')
                    ->label('')
                    ->icon('heroicon-o-x-mark')
                    ->tooltip(__('Undo receiving'))
                    ->color('danger')
                    ->action(function (MutationItem $record, Tables\Actions\Action $action) {
                        if (! $this->selamaMasihDikirim(fn () => $record->update(['is_received' => false]))) {
                            $action->cancel();
                        }
                    })
                    ->successNotificationTitle(__('Receiving undone')),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back'))
                ->url(MutationResource::getUrl('view', ['record' => $this->record->id]))
                ->color('gray'),
                
            Actions\Action::make('receive_all')
                ->label(__('Receive All'))
                ->color('warning')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->action(function () {
                    $ditandai = $this->selamaMasihDikirim(fn () => MutationItem::where('mutation_id', $this->record->id)
                        ->where('is_received', false)
                        ->update(['is_received' => true]));

                    if ($ditandai) {
                        Notification::make('scan-toast')->title(__('Every item has been marked as received'))->success()->send();
                    }
                }),

            Actions\Action::make('finish')
                ->label(__('Complete Receiving'))
                ->color('success')
                ->icon('heroicon-o-inbox-arrow-down')
                ->requiresConfirmation()
                ->modalHeading(__('Finish receiving?'))
                ->modalDescription(__('Everything scanned goes into the destination warehouse. Anything not scanned is left out and recorded as a shortage.'))
                ->action(function () {
                    $kosong = false;

                    // Daftar barang diterima dibaca DI DALAM transaksi yang sama
                    // dengan kunci baris Mutation, supaya dua penekanan tombol
                    // (dua tab, dua orang, atau klik ganda) tidak sama-sama
                    // membuat stok gudang tujuan.
                    $selesai = $this->selamaMasihDikirim(function (Mutation $mutation) use (&$kosong) {
                        $receivedItems = MutationItem::where('mutation_id', $mutation->id)
                            ->where('is_received', true)
                            ->get();

                        if ($receivedItems->isEmpty()) {
                            $kosong = true;

                            return;
                        }

                        foreach ($receivedItems as $item) {
                            \App\Models\BeefStock::create([
                                'barcode' => $item->barcode,
                                'product_id' => $item->product_id,
                                'warehouse_id' => $this->record->to_warehouse_id,
                                'grade_id' => $item->grade_id,
                                'weight' => $item->weight,
                                'qty_pcs' => $item->qty_pcs,
                                'ph_level' => $item->ph_level,
                                'pack_date' => $item->pack_date,
                                'exp_date' => $item->exp_date,
                                'origin' => $item->origin,
                                'status' => 'IN_STOCK',
                                'note' => 'Mutasi Masuk ' . $this->record->mutation_number,
                            ]);

                            \App\Models\BeefStockMovement::create([
                                'product_id' => $item->product_id,
                                'warehouse_id' => $this->record->to_warehouse_id,
                                'condition' => $item->grade_id,
                                'barcode' => $item->barcode,
                                'transaction_type' => 'MUTATION_IN',
                                'reference_document' => $this->record->mutation_number,
                                'weight_in' => $item->weight,
                                'weight_out' => 0,
                                'pcs_in' => $item->qty_pcs,
                                'pcs_out' => 0,
                                'note' => 'Penerimaan Mutasi',
                                'created_by' => auth()->id(),
                            ]);
                        }

                        $mutation->update([
                            'status' => 'COMPLETED',
                            'received_by' => auth()->id()
                        ]);
                    });

                    if (! $selesai) {
                        return;
                    }

                    if ($kosong) {
                        Notification::make('scan-toast')->title(__('No items have been scanned yet!'))->danger()->send();

                        return;
                    }

                    $this->record->refresh();

                    Notification::make('scan-toast')->title(__('Mutation finished; the goods are now in the destination warehouse.'))->success()->send();
                    $this->redirect(MutationResource::getUrl('view', ['record' => $this->record->id]));
                }),
        ];
    }
}
