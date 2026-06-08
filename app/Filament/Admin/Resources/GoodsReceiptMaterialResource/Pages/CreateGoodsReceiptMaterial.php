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
            Notification::make()->title('PO is already completed!')->danger()->send();
            $this->redirect(GoodsReceiptMaterialResource::getUrl('index'));
            return;
        }

        $itemsData = [];
        foreach ($this->purchaseMaterial->items as $item) {
            $itemsData[] = [
                'material_id' => $item->material_id,
                'material_name' => $item->material->name,
                'po_qty' => number_format($item->qty, 2, ',', '.'),
                'qty_received' => number_format($item->qty, 2, ',', '.'),
                'price' => $item->price,
            ];
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
                ->label('Cancel')
                ->color('gray')
                ->url(GoodsReceiptMaterialResource::getUrl('drafts')),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Purchase Order Information')
                    ->description('PO Number: ' . ($this->purchaseMaterial->po_number ?? '-'))
                    ->schema([
                        Forms\Components\TextInput::make('supplier_name')
                            ->label('Supplier')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\DatePicker::make('receive_date')
                            ->label('Receive Date')
                            ->required(),
                        Forms\Components\TextInput::make('sj_number')
                            ->label('Surat Jalan Number')
                            ->nullable(),
                        Forms\Components\Textarea::make('note')
                            ->label('Note')
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('Materials to Receive')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->schema([
                                Forms\Components\TextInput::make('material_name')
                                    ->label('Material')
                                    ->hiddenLabel()
                                    ->placeholder('Material')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\Hidden::make('material_id'),
                                Forms\Components\Hidden::make('price'),
                                Forms\Components\TextInput::make('po_qty')
                                    ->label('Qty in PO')
                                    ->hiddenLabel()
                                    ->placeholder('Qty in PO')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('qty_received')
                                    ->label('Qty Received')
                                    ->hiddenLabel()
                                    ->placeholder('Qty Received')
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 2)'))
                                    ->stripCharacters('.')
                                    ->extraInputAttributes(['x-on:focus' => '$el.select()'])
                                    ->required()
                                    ->live(onBlur: true),
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
            if ($qtyReceived < $item['po_qty']) {
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
            $grNumber = 'GRM-' . date('Ymd') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

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

            // Update PO Status
            $this->purchaseMaterial->update(['status' => $poStatus]);

            DB::commit();

            Notification::make()->title('Goods Receipt created successfully!')->success()->send();
            $this->redirect(GoodsReceiptMaterialResource::getUrl('index'));

        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
        }
    }
}
