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

class ScanMutation extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string $resource = MutationResource::class;

    protected static string $view = 'filament.admin.resources.mutation-resource.pages.scan-mutation';

    public Mutation $record;
    public ?string $barcode = '';

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    public function getHeading(): string
    {
        return 'Scan Mutasi: ' . $this->record->mutation_number;
    }



    public function mount(Mutation $record): void
    {
        $this->record = $record;

        if ($this->record->status !== 'DRAFT') {
            $this->redirect(MutationResource::getUrl('view', ['record' => $this->record->id]));
            return;
        }
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('barcode')
                ->label(__('Scan Barcode'))
                ->placeholder(__('Put the cursor here and start scanning...'))
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
        // Method Livewire biasa, bukan Filament Action -- tidak ada
        // ->hidden()/->authorize() yang bisa dipasang. Sebelumnya
        // satu-satunya gerbang view_mutations (lewat canViewAny()
        // Resource), sekelas persis dengan Tally::scan() sebelum
        // diperbaiki: siapa pun berizin MELIHAT saja bisa mengonsumsi
        // stok lewat scan.
        if (! auth()->user()?->hasPermission('edit_mutations')) {
            Notification::make()
                ->title(__('You do not have permission to scan items'))
                ->danger()
                ->send();

            return;
        }

        $barcode = trim($this->barcode);
        $this->barcode = '';

        if (empty($barcode)) return;



        // Fast-path agar pesannya ramah; penjagaan yang mengikat ada di dalam transaksi.
        if (MutationItem::where('mutation_id', $this->record->id)->where('barcode', $barcode)->exists()) {
            Notification::make()->title(__('This barcode has already been scanned on this mutation.'))->warning()->send();
            $this->dispatch('focus-barcode');
            return;
        }

        try {
            DB::transaction(function () use ($barcode) {
                // TOCTOU: cek duplikat harus di dalam transaksi dan terkunci, supaya
                // dua scan berbarengan tidak sama-sama lolos.
                $alreadyScanned = MutationItem::where('mutation_id', $this->record->id)
                    ->where('barcode', $barcode)
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyScanned) {
                    throw new \Exception(__('This barcode has already been scanned on this mutation.'));
                }

                $stock = BeefStock::where('barcode', $barcode)->lockForUpdate()->first();

                if (!$stock) {
                    throw new \Exception(__('This item is not in stock.'));
                }

                if ($stock->warehouse_id !== $this->record->from_warehouse_id) {
                    throw new \Exception(__('This item does not belong to the source warehouse of this mutation.'));
                }

                MutationItem::create([
                    'mutation_id' => $this->record->id,
                    'barcode' => $barcode,
                    'product_id' => $stock->product_id,
                    'grade_id' => $stock->grade_id,
                    'weight' => $stock->weight,
                    'qty_pcs' => $stock->qty_pcs,
                    'ph_level' => $stock->ph_level,
                    'pack_date' => $stock->pack_date,
                    'exp_date' => $stock->exp_date,
                    'origin' => $stock->origin,
                ]);

                \App\Models\BeefStockMovement::create([
                    'product_id' => $stock->product_id,
                    'warehouse_id' => $stock->warehouse_id,
                    'condition' => $stock->grade_id,
                    'barcode' => $stock->barcode,
                    'transaction_type' => 'MUTATION_OUT',
                    'reference_document' => $this->record->mutation_number,
                    'weight_in' => 0,
                    'weight_out' => $stock->weight,
                    'pcs_in' => 0,
                    'pcs_out' => $stock->qty_pcs,
                    'note' => 'Di-scan untuk mutasi',
                    'created_by' => auth()->id(),
                ]);

                $stock->delete();
            });

            Notification::make()->title(__('Barcode scanned'))->success()->send();
        } catch (\Exception $e) {
            report($e);
            Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();
        }
        
        $this->dispatch('focus-barcode');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(MutationItem::query()->where('mutation_id', $this->record->id))
            ->columns([
                Tables\Columns\TextColumn::make('barcode')->label(__('Barcode')),
                Tables\Columns\TextColumn::make('product.name')->label(__('Product')),
                Tables\Columns\TextColumn::make('weight')->label(__('Qty'))->numeric(2),
                Tables\Columns\TextColumn::make('qty_pcs')->label(__('Pcs')),
                Tables\Columns\TextColumn::make('grade.name')->label(__('Grade')),
                Tables\Columns\TextColumn::make('ph_level')->label(__('pH'))->numeric(1),
                Tables\Columns\TextColumn::make('pack_date')->label(__('POD'))->date('d M Y'),
                Tables\Columns\TextColumn::make('origin')->label(__('Origin')),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->icon('heroicon-o-trash')
                    ->tooltip(__('Delete'))
                    ->hidden(fn () => ! auth()->user()?->hasPermission('edit_mutations'))
                    ->authorize(fn (): bool => auth()->user()?->hasPermission('edit_mutations') ?? false)
                    ->action(function (MutationItem $record) {
                        $ditolak = false;

                        DB::transaction(function () use ($record, &$ditolak) {
                            // Baris Mutation dikunci dan statusnya dibaca
                            // ULANG dari basis data sebelum baris item
                            // dihapus. Tanpa ini, tab yang masih terbuka
                            // saat mutasinya sudah SENT/RECEIVED di sesi
                            // lain tetap bisa unscan -- MutationItem::deleted()
                            // otomatis mengembalikan barcode itu ke
                            // beef_stocks (gudang ASAL) padahal fisiknya
                            // sudah dalam perjalanan atau sudah diterima.
                            $mutation = Mutation::whereKey($record->mutation_id)->lockForUpdate()->first();

                            if (! $mutation || $mutation->status !== 'DRAFT') {
                                $ditolak = true;

                                return;
                            }

                            $record->delete();
                        });

                        if ($ditolak) {
                            Notification::make()
                                ->title(__('This mutation can no longer be edited'))
                                ->body(__('It has already been sent or received, so items can no longer be removed from it.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('Item removed and returned to stock'))
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back'))
                ->url(MutationResource::getUrl('index'))
                ->color('gray'),
            Actions\Action::make('finish')
                ->label(__('Complete Scan'))
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                // Mutasi KOSONG tidak bisa dikirim.
                //
                // Tanpa penjagaan ini, dokumen tanpa satu pun barang bisa
                // berstatus SENT, lalu diterima di gudang tujuan, lalu
                // selesai -- dokumen lengkap yang tidak memindahkan apa pun.
                ->disabled(fn (): bool => $this->record->items()->doesntExist())
                ->action(function () {
                    if ($this->record->items()->doesntExist()) {
                        Notification::make()
                            ->title(__('Nothing has been scanned yet'))
                            ->body(__('Scan at least one item before sending this mutation.'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record->update(['status' => 'SENT']);
                    Notification::make()->title(__('Mutation locked and sent'))->success()->send();
                    $this->redirect(MutationResource::getUrl('view', ['record' => $this->record->id]));
                }),
        ];
    }
}
