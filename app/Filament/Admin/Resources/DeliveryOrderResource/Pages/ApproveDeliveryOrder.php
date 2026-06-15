<?php

namespace App\Filament\Admin\Resources\DeliveryOrderResource\Pages;

use App\Filament\Admin\Resources\DeliveryOrderResource;
use App\Models\DeliveryOrder;
use App\Models\TallyItem;
use App\Models\DeliveryOrderReceipt;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class ApproveDeliveryOrder extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string $resource = DeliveryOrderResource::class;

    protected static string $view = 'filament.admin.resources.delivery-order-resource.pages.approve-delivery-order';

    public $record;

    public ?array $data = [];

    public function mount($record): void
    {
        $this->record = DeliveryOrder::findOrFail($record);

        if ($this->record->status !== 'Ready') {
            Notification::make()
                ->title(__('Hanya Delivery Order status Ready yang bisa di-approve'))
                ->danger()
                ->send();

            $this->redirect(DeliveryOrderResource::getUrl('edit', ['record' => $this->record->id]));
            return;
        }

        $this->fillForm();
    }

    public function fillForm(): void
    {
        $this->record->load('items');

        $items = [];
        foreach ($this->record->items as $item) {
            $items[] = [
                'product_id' => $item->product_id,
                'shipped_box' => $item->box,
                'shipped_weight' => (float)$item->weight,
                'box' => $item->box,
                'weight' => (float)$item->weight,
                'notes' => $item->notes,
            ];
        }

        $this->form->fill([
            'receipt_items' => $items,
            'receipt_note' => $this->record->note,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Pemeriksaan Penerimaan (Checking)'))
                    ->schema([
                        Forms\Components\Repeater::make('receipt_items')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label(__('Product'))
                                    ->options(\App\Models\Product::pluck('name', 'id'))
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('shipped_weight')
                                    ->label(__('Shipped Weight'))
                                    ->disabled()
                                    ->numeric()
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('weight')
                                    ->label(__('Received Weight'))
                                    ->required()
                                    ->numeric()
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('notes')
                                    ->label(__('Notes'))
                                    ->columnSpan(12),
                            ])
                            ->columns(12)
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->disableItemMovement()
                            ->hiddenLabel(),

                        Forms\Components\Textarea::make('receipt_note')
                            ->label(__('Receipt Note'))
                            ->placeholder(__('Catatan Penerimaan'))
                            ->columnSpanFull(),
                    ])
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('rejections')
                ->label(__('Scan Tolakan (Rejections)'))
                ->color('warning')
                ->icon('heroicon-o-arrow-path')
                ->modalWidth('4xl')
                ->modalHeading(__('Pengembalian Barang ke Stock (Rejections)'))
                ->form([
                    Forms\Components\TextInput::make('barcode_scan')
                        ->label(__('Scan Barcode'))
                        ->placeholder(__('Scan barcode tolakan di sini...'))
                        ->autofocus()
                        ->extraAttributes([
                            'onkeydown' => 'if (event.key === "Enter") { event.preventDefault(); document.getElementById("add-barcode-btn")?.click(); }'
                        ])
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('add_barcode')
                                ->icon('heroicon-m-plus')
                                ->extraAttributes(['id' => 'add-barcode-btn'])
                                ->action(function (Forms\Set $set, Forms\Get $get) {
                                    $barcode = trim($get('barcode_scan'));
                                    if (!$barcode) return;

                                    $tallyItems = $this->record->tally?->items ?? collect();
                                    $matchingItem = $tallyItems->firstWhere('barcode', $barcode);
                                    if ($matchingItem) {
                                        $rejected = $get('rejected_barcodes') ?? [];
                                        if (!in_array($barcode, $rejected)) {
                                            $rejected[] = $barcode;
                                            $set('rejected_barcodes', $rejected);

                                            $count = count($rejected);
                                            $set('scanned_count_placeholder', "Total Ter-scan: {$count} karton");

                                            Notification::make()
                                                ->title(__('Barcode tercentang'))
                                                ->success()
                                                ->send();
                                        } else {
                                            Notification::make()
                                                ->title(__('Barcode sudah tercentang'))
                                                ->warning()
                                                ->send();
                                        }
                                    } else {
                                        Notification::make()
                                            ->title(__('Barcode tidak ditemukan di Tally ini'))
                                            ->danger()
                                            ->send();
                                    }
                                    $set('barcode_scan', '');
                                })
                        ),

                    Forms\Components\Placeholder::make('scanned_count_placeholder')
                        ->label('')
                        ->content(function (Forms\Get $get) {
                            $rejected = $get('rejected_barcodes') ?? [];
                            $count = count($rejected);
                            return new \Illuminate\Support\HtmlString("<div class='text-lg font-bold text-warning-600 dark:text-warning-400'>Total Ter-scan: {$count} karton</div>");
                        }),

                    Forms\Components\CheckboxList::make('rejected_barcodes')
                        ->label(__('Pilih Barcode Tolakan'))
                        ->options(fn () => $this->record->tally?->items->mapWithKeys(fn ($item) => [
                            $item->barcode => $item->barcode . ' (' . $item->product->name . ' - ' . number_format($item->weight, 2) . ' kg)'
                        ])->toArray() ?? [])
                        ->columns(2)
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                            $count = count($state ?? []);
                            $set('scanned_count_placeholder', "Total Ter-scan: {$count} karton");
                        }),
                ])
                ->action(function (array $data) {
                    $barcodes = $data['rejected_barcodes'] ?? [];
                    if (empty($barcodes)) {
                        Notification::make()
                            ->title(__('Tidak ada barang yang dipilih'))
                            ->warning()
                            ->send();
                        return;
                    }

                    DB::transaction(function () use ($barcodes) {
                        TallyItem::where('tally_id', $this->record->tally_id)
                            ->whereIn('barcode', $barcodes)
                            ->get()
                            ->each->delete();

                        $this->record->syncItemsFromTally();
                    });

                    Notification::make()
                        ->title(__('Tolakan berhasil diproses'))
                        ->body(__('Barang yang ditolak telah dikembalikan ke stok.'))
                        ->success()
                        ->send();

                    $this->fillForm();
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('submit')
                ->label(__('Approve DO'))
                ->color('success')
                ->submit('form'),

            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->color('gray')
                ->url(fn () => DeliveryOrderResource::getUrl('edit', ['record' => $this->record->id])),
        ];
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        DB::transaction(function () use ($data) {
            $receiptNumber = str_replace('SWM-DO#', 'SWM-REC#', $this->record->delivery_order_number);

            $doItemsList = $this->record->items->values();

            $totalBox = 0;
            $totalWeight = 0;
            $index = 0;
            foreach ($data['receipt_items'] as $key => $item) {
                $doItem = $doItemsList[$index] ?? null;
                $productId = $item['product_id'] ?? ($doItem ? $doItem->product_id : null);
                $boxCount = $item['box'] ?? ($doItem ? $doItem->box : 0);
                
                $data['receipt_items'][$key]['product_id'] = $productId;
                $data['receipt_items'][$key]['box'] = $boxCount;
                
                $totalBox += $boxCount;
                $totalWeight += (float)$item['weight'];
                $index++;
            }

            $receipt = DeliveryOrderReceipt::create([
                'delivery_order_id' => $this->record->id,
                'sales_order_id' => $this->record->sales_order_id,
                'customer_id' => $this->record->customer_id,
                'receipt_number' => $receiptNumber,
                'delivery_date' => $this->record->delivery_date,
                'po_number' => $this->record->po_number,
                'note' => $data['receipt_note'] ?? null,
                'total_box' => $totalBox,
                'total_weight' => $totalWeight,
                'status' => 'Approved',
                'created_by' => auth()->id(),
            ]);

            foreach ($data['receipt_items'] as $item) {
                $receipt->items()->create([
                    'product_id' => $item['product_id'],
                    'box' => $item['box'],
                    'weight' => $item['weight'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $this->record->update(['status' => 'Approved']);

            // Calculate financial loss for qty adjustments (shipped_weight vs weight)
            $doItems = $this->record->items->keyBy('product_id');
            $totalLossWeight = 0.0;
            foreach ($data['receipt_items'] as $item) {
                $productId = $item['product_id'];
                $receivedWeight = (float)$item['weight'];
                $shippedWeight = isset($doItems[$productId]) ? (float)$doItems[$productId]->weight : 0.0;
                
                if ($receivedWeight < $shippedWeight) {
                    $totalLossWeight += ($shippedWeight - $receivedWeight);
                }
            }

            if ($totalLossWeight > 0) {
                $this->record->financialLoss()->updateOrCreate(
                    [
                        'transaction_type' => 'Delivery Order',
                        'reference_number' => $this->record->delivery_order_number,
                    ],
                    [
                        'date' => $this->record->delivery_date,
                        'amount' => 0.00,
                        'note' => 'Susut Kirim DO: ' . $this->record->delivery_order_number . ' sebesar ' . number_format($totalLossWeight, 2) . ' Kg',
                    ]
                );
            } else {
                $this->record->financialLoss()->delete();
            }

            if ($this->record->salesOrder) {
                $this->record->salesOrder->update(['status' => 'completed']);
            }
        });

        Notification::make()
            ->title(__('Delivery Order Approved & Receipt Created'))
            ->success()
            ->send();

        $this->redirect(DeliveryOrderResource::getUrl('index'));
    }
}
