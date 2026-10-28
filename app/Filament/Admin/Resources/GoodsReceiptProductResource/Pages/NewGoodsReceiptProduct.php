<?php

namespace App\Filament\Admin\Resources\GoodsReceiptProductResource\Pages;

use App\Filament\Admin\Resources\GoodsReceiptProductResource;
use App\Models\GoodsReceiptProduct;
use App\Models\PurchaseProduct;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * Formulir penerimaan beef BARU untuk satu PO. Dokumennya (nomor GRB) baru lahir saat Simpan, bukan saat tombol Process GR
 * ditekan (keputusan Owner 28 Oktober 2026): klik yang ditinggalkan tidak meninggalkan GRB kosong, dan dua orang yang
 * membuka PO yang sama tidak melahirkan dua GRB. Saat simpan, PO dikunci dan diperiksa ulang: kalau sejak formulir dibuka
 * sudah ada penerimaan lain untuk PO itu, simpanan ditolak.
 */
class NewGoodsReceiptProduct extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = GoodsReceiptProductResource::class;

    protected static string $view = 'filament.admin.resources.goods-receipt-product-resource.pages.new-goods-receipt-product';

    public PurchaseProduct $purchaseProduct;

    public ?array $data = [];

    /** Jumlah penerimaan PO ini saat formulir dibuka -- berubah berarti orang lain sudah mulai menerima. */
    public int $receiptsAtOpen = 0;

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('edit_goods_receipt_products') ?? false);
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Input Goods Receipt Product');
    }

    public function mount(PurchaseProduct $purchaseProduct): void
    {
        $this->purchaseProduct = $purchaseProduct;

        if (! in_array($purchaseProduct->status, ['pending', 'partial'], true)) {
            Notification::make()->title(__('This purchase order is no longer waiting for a goods receipt.'))->warning()->send();
            $this->redirect(GoodsReceiptProductResource::getUrl('drafts'));

            return;
        }

        $this->receiptsAtOpen = GoodsReceiptProduct::where('purchase_product_id', $purchaseProduct->id)->count();

        $this->form->fill([
            'receive_date' => now()->format('Y-m-d'),
            'supplier_name' => $purchaseProduct->supplier->name ?? '-',
            'po_number' => $purchaseProduct->po_number ?? '-',
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
                            ->required(),
                        Forms\Components\TextInput::make('supplier_name')
                            ->label(__('Supplier Name'))
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('sj_number')
                            ->label(__('Delivery Number'))
                            ->placeholder(__('Leave it empty if there is none')),
                        Forms\Components\TextInput::make('po_number')
                            ->label(__('PO Number'))
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('note')
                            ->label(__('Note for this goods receipt'))
                            ->placeholder(__('Note for this goods receipt'))
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label(__('Back'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => GoodsReceiptProductResource::getUrl('drafts')),
        ];
    }

    public function getPoItemsProperty()
    {
        return $this->purchaseProduct->items()->with('product')->get();
    }

    public function saveGr(): void
    {
        if (! static::canAccess()) {
            Notification::make()->title(__('You do not have permission to do this.'))->danger()->send();

            return;
        }

        $this->validate();

        $formData = $this->form->getState();

        $gr = null;

        try {
            $gr = DB::transaction(function () use ($formData): ?GoodsReceiptProduct {
                $po = PurchaseProduct::whereKey($this->purchaseProduct->id)->lockForUpdate()->first();

                $now = GoodsReceiptProduct::where('purchase_product_id', $this->purchaseProduct->id)->count();

                if (! $po || ! in_array($po->status, ['pending', 'partial'], true) || $now !== $this->receiptsAtOpen) {
                    return null;
                }

                $gr = GoodsReceiptProduct::create([
                    'purchase_product_id' => $po->id,
                    'supplier_id' => $po->supplier_id,
                    'receive_date' => $formData['receive_date'],
                    'sj_number' => $formData['sj_number'] ?? null,
                    'note' => $formData['note'] ?? null,
                    'created_by' => auth()->id(),
                ]);

                $po->update(['status' => 'completed']);

                return $gr;
            });
        } catch (\Exception $e) {
            report($e);
            Notification::make()->title('Error: '.$e->getMessage())->danger()->send();

            return;
        }

        if (! $gr) {
            Notification::make()
                ->title(__('Someone else has already started receiving this purchase order'))
                ->body(__('Open it from the goods receipt list instead of creating another one.'))
                ->danger()
                ->send();
            $this->redirect(GoodsReceiptProductResource::getUrl('index'));

            return;
        }

        Notification::make()->title(__('Goods receipt header saved'))->success()->send();
        $this->redirect(GoodsReceiptProductResource::getUrl('input', ['record' => $gr->id]).'?next=1');
    }
}
