<?php

namespace App\Filament\Admin\Resources\GoodsReceiptProductResource\Pages;

use App\Filament\Admin\Resources\GoodsReceiptProductResource;
use App\Models\GoodsReceiptProduct;
use Filament\Resources\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Actions;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class InputGoodsReceiptProduct extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = GoodsReceiptProductResource::class;

    protected static string $view = 'filament.admin.resources.goods-receipt-product-resource.pages.input-goods-receipt-product';

    public GoodsReceiptProduct $record;
    public ?array $data = [];

    public function mount(GoodsReceiptProduct $record): void
    {
        $this->record = $record;

        $this->form->fill([
            'receive_date' => $record->receive_date ?? now()->format('Y-m-d'),
            'supplier_name' => $record->supplier->name ?? '-',
            'sj_number' => $record->sj_number,
            'po_number' => $record->purchaseProduct->po_number ?? '-',
            'note' => $record->note,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\DatePicker::make('receive_date')
                            ->label(__('Receiving Date'))
                            ->required()
                            ->disabled(fn () => $this->record->is_locked),
                        Forms\Components\TextInput::make('supplier_name')
                            ->label(__('Supplier Name'))
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('sj_number')
                            ->label(__('Delivery Number'))
                            ->placeholder(__('Biarkan Kosong Jika Tidak Ada'))
                            ->disabled(fn () => $this->record->is_locked),
                        Forms\Components\TextInput::make('po_number')
                            ->label(__('PO Number'))
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('note')
                            ->label(__('Catatan Untuk GR'))
                            ->placeholder(__('Catatan Untuk GR'))
                            ->columnSpanFull()
                            ->disabled(fn () => $this->record->is_locked),
                    ])
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Back'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => GoodsReceiptProductResource::getUrl('index')),

            Actions\Action::make('lock')
                ->tooltip(__('Lock'))
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->hiddenLabel()
                ->requiresConfirmation()
                ->modalHeading(__('Lock Goods Receipt'))
                ->modalDescription(__('Apakah Anda yakin ingin mengunci GR ini? Data tidak akan bisa diubah setelah dikunci (GR Selesai).'))
                ->hidden(fn () => $this->record->is_locked || ! $this->record->items()->exists())
                ->action(fn () => $this->lockGr()),

            Actions\Action::make('print')
                ->tooltip(__('Print'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->hiddenLabel()
                ->disabled(fn () => ! $this->record->is_locked)
                ->hidden(fn () => ! $this->record->items()->exists())
                ->url(fn () => route('goods-receipt-product.print', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('delete')
                ->tooltip(__('Delete'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->hiddenLabel()
                ->requiresConfirmation()
                ->modalHeading(__('Delete Goods Receipt'))
                ->modalDescription(__('Apakah Anda yakin ingin menghapus GR ini?'))
                ->hidden(fn () => $this->record->items()->exists())
                ->action(fn () => $this->deleteGr()),
        ];
    }

    public function saveGr(): void
    {
        if ($this->record->is_locked) {
            Notification::make()->title('Tidak dapat menyimpan karena GR sudah dikunci.')->warning()->send();
            return;
        }

        $this->validate();

        $formData = $this->form->getState();

        DB::beginTransaction();
        try {
            $this->record->update([
                'receive_date' => $formData['receive_date'],
                'sj_number' => $formData['sj_number'],
                'note' => $formData['note'] ?? null,
            ]);

            if ($this->record->purchaseProduct) {
                $this->record->purchaseProduct->update(['status' => 'completed']);
            }

            DB::commit();

            Notification::make()->title('Goods Receipt header berhasil disimpan!')->success()->send();
            $this->record->refresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
        }
    }

    public function lockGr(): void
    {
        if ($this->record->is_locked) {
            return;
        }

        DB::beginTransaction();
        try {
            $this->record->update(['is_locked' => true]);

            // Generate account payable
            \App\Models\Payable::generateForGoodsReceiptProduct($this->record);

            DB::commit();

            Notification::make()->title(__('Goods Receipt berhasil dikunci (gr selesai)!'))->success()->send();
            $this->redirect(GoodsReceiptProductResource::getUrl('index'));
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
        }
    }

    public function deleteGr(): void
    {
        if ($this->record->items()->exists()) {
            Notification::make()->title('Tidak dapat menghapus GR karena sudah ada barang di detail.')->warning()->send();
            return;
        }

        DB::beginTransaction();
        try {
            if ($this->record->purchaseProduct) {
                $this->record->purchaseProduct->update(['status' => 'pending']);
            }

            $this->record->forceDelete();

            DB::commit();

            Notification::make()->title('Goods Receipt berhasil dihapus dan Purchase Order dikembalikan ke status pending.')->success()->send();
            $this->redirect(GoodsReceiptProductResource::getUrl('index'));
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
        }
    }

    public function goScan(): void
    {
        $this->redirect(GoodsReceiptProductResource::getUrl('scan', ['record' => $this->record->id]));
    }

    public function goLabel(): void
    {
        $this->redirect(GoodsReceiptProductResource::getUrl('labeling', ['record' => $this->record->id]));
    }

    public function getPoItemsWithReceiptProperty()
    {
        $poItems = $this->record->purchaseProduct->items()->with('product')->get();
        
        $receivedData = $this->record->items()
            ->select('product_id', DB::raw('SUM(weight) as total_weight'), DB::raw('SUM(qty_pcs) as total_pcs'))
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        return $poItems->map(function ($poItem) use ($receivedData) {
            $received = $receivedData->get($poItem->product_id);
            return [
                'product_name' => $poItem->product->name,
                'order_qty' => $poItem->qty,
                'receive_weight' => $received ? $received->total_weight : 0,
                'receive_pcs' => $received ? $received->total_pcs : 0,
            ];
        });
    }

    public function getTableTotalsProperty()
    {
        $poItemsWithReceipt = $this->poItemsWithReceipt;
        return [
            'total_order_qty' => $poItemsWithReceipt->sum('order_qty'),
            'total_receive_weight' => $poItemsWithReceipt->sum('receive_weight'),
            'total_receive_pcs' => $poItemsWithReceipt->sum('receive_pcs'),
        ];
    }

    public function getHasItemsProperty(): bool
    {
        return $this->record->items()->exists();
    }
}
