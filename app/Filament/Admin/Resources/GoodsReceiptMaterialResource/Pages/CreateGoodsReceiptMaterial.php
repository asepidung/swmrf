<?php

namespace App\Filament\Admin\Resources\GoodsReceiptMaterialResource\Pages;

use App\Filament\Admin\Resources\GoodsReceiptMaterialResource;
use App\Models\PurchaseMaterial;
use App\Models\GoodsReceiptMaterial;
use Filament\Resources\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Illuminate\Support\Str;
use Filament\Support\RawJs;

class CreateGoodsReceiptMaterial extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = GoodsReceiptMaterialResource::class;

    protected static string $view = 'filament.admin.resources.goods-receipt-material-resource.pages.create-goods-receipt-material';

    public ?array $data = [];
    public ?int $poId = null;
    public ?PurchaseMaterial $purchaseMaterial = null;

    public bool $showPartialModal = false;

    public function mount(): void
    {
        $this->poId = request()->query('po_id');

        if (!$this->poId) {
            $this->redirect(GoodsReceiptMaterialResource::getUrl('drafts'));
            return;
        }

        $this->purchaseMaterial = PurchaseMaterial::with('items.material')->findOrFail($this->poId);

        if (!in_array($this->purchaseMaterial->status, ['pending', 'partial'])) {
            Notification::make()->title(__('PO is already completed!'))->danger()->send();
            $this->redirect(GoodsReceiptMaterialResource::getUrl('index'));
            return;
        }

        $itemsData = [];
        foreach ($this->purchaseMaterial->items as $item) {
            $previouslyReceived = \App\Models\GoodsReceiptMaterialItem::whereHas('goodsReceiptMaterial', function ($query) {
                $query->where('purchase_material_id', $this->poId);
            })->where('material_id', $item->material_id)->sum('qty_received');

            $remainingQty = $item->qty - $previouslyReceived;

            if ($remainingQty > 0) {
                $itemsData[] = [
                    'material_id' => $item->material_id,
                    'material_name' => $item->material->name,
                    'unit_name' => $item->material->unit?->name ?? '',
                    'po_qty' => number_format($remainingQty, 2, ',', '.'),
                    'qty_received' => number_format($remainingQty, 2, ',', '.'),
                    'price' => $item->price,
                ];
            }
        }

        if (empty($itemsData)) {
            $this->purchaseMaterial->update(['status' => 'completed']);
            Notification::make()->title(__('All items for this PO have already been completely received!'))->warning()->send();
            $this->redirect(GoodsReceiptMaterialResource::getUrl('index'));
            return;
        }

        $this->form->fill([
            'supplier_name' => $this->purchaseMaterial->supplier->name ?? '-',
            'receive_date' => null,
            'sj_number' => null,
            'note' => '',
            'items' => $itemsData,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel')
                ->label(__('Cancel'))
                ->color('gray')
                ->url(GoodsReceiptMaterialResource::getUrl('drafts')),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Purchase Order Information'))
                    ->description('PO Number: ' . ($this->purchaseMaterial->po_number ?? '-'))
                    ->schema([
                        Forms\Components\TextInput::make('supplier_name')
                            ->label(__('Supplier'))
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\DatePicker::make('receive_date')
                            ->label(__('Receive Date'))
                            ->required(),
                        Forms\Components\TextInput::make('sj_number')
                            ->label(__('Surat Jalan Number'))
                            ->nullable(),
                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make(__('Materials to Receive'))
                    ->schema([
                        // Clean Repeater Header UI
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('col_material')->label(__('Material')),
                                Forms\Components\Placeholder::make('col_po_qty')->label(__('Qty PO')),
                                Forms\Components\Placeholder::make('col_qty_received')->label(__('Qty Received')),
                            ])
                            ->extraAttributes(['class' => 'hidden md:grid']),

                        Forms\Components\Repeater::make('items')
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\TextInput::make('material_name')
                                    ->label(__('Material'))
                                    ->hiddenLabel()
                                    ->placeholder(__('Material'))
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\Hidden::make('material_id'),
                                Forms\Components\Hidden::make('unit_name'),
                                Forms\Components\Hidden::make('price'),
                                Forms\Components\TextInput::make('po_qty')
                                    ->label(__('Qty in PO'))
                                    ->hiddenLabel()
                                    ->placeholder(__('Qty in PO'))
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->suffix(fn (Forms\Get $get) => $get('unit_name')),
                                Forms\Components\TextInput::make('qty_received')
                                    ->label(__('Qty Received'))
                                    ->hiddenLabel()
                                    ->placeholder(__('Qty Received'))
                                    ->numeric()
                                                                        ->extraInputAttributes(['x-on:focus' => '$el.select()'])
                                    ->required()
                                    ->live(onBlur: true)
                                    ->suffix(fn (Forms\Get $get) => $get('unit_name')),
                            ])
                            ->columns(3)
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->disableItemMovement(),
                    ]),
            ])
            ->statePath('data');
    }

    public static function parseNumber($value): float
    {
        if (blank($value)) return 0.0;
        $val = (string) $value;

        if (preg_match('/^-?\d+(\.\d{1,2})?$/', $val)) {
            return (float) $val;
        }

        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);
        return (float) $val;
    }

    public function processSave(): void
    {
        $data = $this->form->getState();
        
        $isPartial = false;
        foreach ($data['items'] as $item) {
            $qtyReceived = self::parseNumber($item['qty_received']);
            $poQty = self::parseNumber($item['po_qty']);
            if ($qtyReceived < $poQty) {
                $isPartial = true;
                break;
            }
        }

        if ($isPartial) {
            $this->dispatch('open-modal', id: 'partial-confirmation-modal');
        } else {
            $this->executeCreate('completed');
        }
    }

    public function confirmPartial()
    {
        $this->executeCreate('partial');
        $this->dispatch('close-modal', id: 'partial-confirmation-modal');
    }

    public function forceCompleted()
    {
        $this->executeCreate('completed');
        $this->dispatch('close-modal', id: 'partial-confirmation-modal');
    }

    protected function executeCreate(string $poStatus): void
    {
        $data = $this->form->getState();

        DB::beginTransaction();
        try {
            // Generate GR Number
            $latest = GoodsReceiptMaterial::latest('id')->first();
            $nextId = $latest ? $latest->id + 1 : 1;
            $grNumber = 'SWM-GRM#' . date('y') . str_pad($nextId, 3, '0', STR_PAD_LEFT);

            $gr = GoodsReceiptMaterial::create([
                'gr_number' => $grNumber,
                'purchase_material_id' => $this->purchaseMaterial->id,
                'supplier_id' => $this->purchaseMaterial->supplier_id,
                'receive_date' => $data['receive_date'],
                'sj_number' => $data['sj_number'],
                'note' => $data['note'],
                'created_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $item) {
                $qtyReceived = self::parseNumber($item['qty_received']);
                if ($qtyReceived > 0) {
                    $gr->items()->create([
                        'material_id' => $item['material_id'],
                        'qty_received' => $qtyReceived,
                        'price' => $item['price'],
                        'subtotal' => $qtyReceived * $item['price'],
                    ]);
                }
            }

            // Payable will be generated when the GR is locked

            // Update PO Status
            $this->purchaseMaterial->update(['status' => $poStatus]);

            DB::commit();

            Notification::make()->title(__('Goods Receipt created successfully!'))->success()->send();
            $this->redirect(GoodsReceiptMaterialResource::getUrl('index'));

        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Save Goods Receipt'))
                ->color('primary')
                ->submit('processSave')
        ];
    }
}
