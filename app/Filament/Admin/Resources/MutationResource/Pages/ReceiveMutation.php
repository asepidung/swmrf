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
        return 'Penerimaan Mutasi: ' . $this->record->mutation_number;
    }

    public function mount(Mutation $record): void
    {
        $this->record = $record;

        if ($this->record->status !== 'SENT') {
            Notification::make()->title('Mutasi belum dikirim atau sudah selesai.')->warning()->send();
            $this->redirect(MutationResource::getUrl('view', ['record' => $this->record->id]));
            return;
        }
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('barcode')
                ->label('Scan Barcode')
                ->placeholder('Arahkan kursor ke sini dan mulai scan penerimaan...')
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
            Notification::make()->title('Barcode tidak ditemukan di mutasi ini!')->danger()->send();
            $this->dispatch('focus-barcode');
            return;
        }

        if ($item->is_received) {
            Notification::make()->title('Barcode ini sudah di-scan (diterima)!')->warning()->send();
            $this->dispatch('focus-barcode');
            return;
        }

        $item->update(['is_received' => true]);

        Notification::make()->title('Sukses di-scan')->success()->send();
        $this->dispatch('focus-barcode');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(MutationItem::query()->where('mutation_id', $this->record->id)->where('is_received', true))
            ->columns([
                Tables\Columns\TextColumn::make('barcode')->label('Barcode'),
                Tables\Columns\TextColumn::make('product.name')->label('Product'),
                Tables\Columns\TextColumn::make('weight')->label('Qty')->numeric(2),
                Tables\Columns\TextColumn::make('qty_pcs')->label('Pcs'),
                Tables\Columns\TextColumn::make('grade.name')->label('Grade'),
                Tables\Columns\TextColumn::make('ph_level')->label('pH')->numeric(1),
                Tables\Columns\TextColumn::make('pack_date')->label('POD')->date('d M Y'),
                Tables\Columns\TextColumn::make('origin')->label('Origin'),
            ])
            ->actions([
                Tables\Actions\Action::make('cancel_receive')
                    ->label('')
                    ->icon('heroicon-o-x-mark')
                    ->tooltip('Batal Terima')
                    ->color('danger')
                    ->action(function (MutationItem $record) {
                        $record->update(['is_received' => false]);
                    })
                    ->successNotificationTitle('Penerimaan item dibatalkan'),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Kembali')
                ->url(MutationResource::getUrl('view', ['record' => $this->record->id]))
                ->color('gray'),
                
            Actions\Action::make('receive_all')
                ->label('Terima Semua')
                ->color('warning')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->action(function () {
                    MutationItem::where('mutation_id', $this->record->id)
                        ->where('is_received', false)
                        ->update(['is_received' => true]);
                    Notification::make()->title('Semua item ditandai sebagai diterima')->success()->send();
                }),

            Actions\Action::make('finish')
                ->label('Selesai Penerimaan')
                ->color('success')
                ->icon('heroicon-o-inbox-arrow-down')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Selesai Penerimaan')
                ->modalDescription('Apakah Anda yakin ingin menyelesaikan penerimaan? Barang yang sudah di-scan akan masuk ke stok gudang tujuan. Barang yang belum di-scan tidak akan dimasukkan (akan dicatat sebagai selisih/hilang).')
                ->action(function () {
                    $receivedItems = MutationItem::where('mutation_id', $this->record->id)
                        ->where('is_received', true)
                        ->get();

                    if ($receivedItems->isEmpty()) {
                        Notification::make()->title('Belum ada barang yang di-scan!')->danger()->send();
                        return;
                    }

                    DB::transaction(function () use ($receivedItems) {
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
                                'created_by' => auth()->id() ?? 1,
                            ]);
                        }
                        
                        $this->record->update([
                            'status' => 'COMPLETED',
                            'received_by' => auth()->id() ?? 1
                        ]);
                    });

                    Notification::make()->title('Mutasi selesai dan barang masuk ke stok gudang.')->success()->send();
                    $this->redirect(MutationResource::getUrl('view', ['record' => $this->record->id]));
                }),
        ];
    }
}
