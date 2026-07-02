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
                ->label('Scan Barcode')
                ->placeholder('Arahkan kursor ke sini dan mulai scan...')
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



        if (MutationItem::where('mutation_id', $this->record->id)->where('barcode', $barcode)->exists()) {
            Notification::make()->title('Barcode sudah di-scan di mutasi ini!')->warning()->send();
            $this->dispatch('focus-barcode');
            return;
        }

        $stock = BeefStock::where('barcode', $barcode)->first();

        if (!$stock) {
            Notification::make()->title('Barang tidak ditemukan di stok!')->danger()->send();
            $this->dispatch('focus-barcode');
            return;
        }

        if ($stock->warehouse_id !== $this->record->from_warehouse_id) {
            Notification::make()->title('Barang ini bukan dari Gudang Asal mutasi!')->danger()->send();
            $this->dispatch('focus-barcode');
            return;
        }

        DB::transaction(function () use ($stock, $barcode) {
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

        Notification::make()->title('Sukses ditambahkan')->success()->send();
        $this->dispatch('focus-barcode');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(MutationItem::query()->where('mutation_id', $this->record->id))
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
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->icon('heroicon-o-trash')
                    ->tooltip('Delete')
                    ->successNotificationTitle('Barang dihapus & dikembalikan ke IN_STOCK'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Kembali')
                ->url(MutationResource::getUrl('view', ['record' => $this->record->id]))
                ->color('gray'),
            Actions\Action::make('finish')
                ->label('Selesai Scan')
                ->url(MutationResource::getUrl('view', ['record' => $this->record->id]))
                ->color('success')
                ->icon('heroicon-o-check-circle'),
        ];
    }
}
