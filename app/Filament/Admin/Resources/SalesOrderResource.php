<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SalesOrderResource\Pages;
use App\Models\SalesOrder;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PriceList;
use App\Models\PriceListItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\RawJs;
use Illuminate\Support\Str;

class SalesOrderResource extends Resource
{
    protected static ?string $model = SalesOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'SALES';
    protected static ?string $navigationLabel = 'Sales Order';
    protected static ?string $pluralModelLabel = 'Sales Orders';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Order Information'))
                    ->compact()
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label(__('Customer'))
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                \Illuminate\Support\Facades\Log::info("customer_id afterStateUpdated: state=" . var_export($state, true));
                                if ($state) {
                                    $customer = Customer::find($state);
                                    if ($customer) {
                                        $set('shipping_address', $customer->alamat1 ?? $customer->address ?? '');
                                    }
                                }

                                // Update prices in existing items
                                $items = $get('items') ?? [];
                                foreach ($items as $key => $item) {
                                    if (!empty($item['product_id'])) {
                                        $newPrice = static::calculateProductPrice($state, $item['product_id']);
                                        $set("items.{$key}.price", number_format($newPrice, 0, '', '.'));
                                    }
                                }
                            }),

                        Forms\Components\DatePicker::make('delivery_date')
                            ->label(__('Delivery Date'))
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('po_number')
                            ->label(__('PO Number'))
                            ->maxLength(255),

                        Forms\Components\Textarea::make('shipping_address')
                            ->label(__('Shipping Address'))
                            ->rows(2)
                            ->columnSpan(2),

                        Forms\Components\Textarea::make('note')
                            ->label(__('Note'))
                            ->rows(2)
                            ->columnSpan(1),

                        Forms\Components\Hidden::make('created_by')
                            ->default(fn() => auth()->id()),
                    ])->columns(3),

                Forms\Components\Section::make(__('Products Detail'))
                    ->compact()
                    ->extraAttributes([
                        'x-on:keydown.enter' => '
                            (function(e) {
                                let target = e.target;
                                if (!target) return;
                                
                                let classes = ["so-weight-input-column", "so-price-input-column", "so-discount-input-column", "so-note-input-column"];
                                let foundClass = classes.find(c => target.classList.contains(c));
                                
                                if (foundClass) {
                                    e.preventDefault();
                                    let inputs = Array.from(document.querySelectorAll("." + foundClass));
                                    let idx = inputs.indexOf(target);
                                    if (idx !== -1 && inputs[idx + 1]) {
                                        inputs[idx + 1].focus();
                                        if (typeof inputs[idx + 1].select === "function") {
                                            inputs[idx + 1].select();
                                        }
                                    }
                                } else {
                                    let selectWrapper = target.closest(".so-product-select-column");
                                    if (selectWrapper) {
                                        e.preventDefault();
                                        let wrappers = Array.from(document.querySelectorAll(".so-product-select-column"));
                                        let idx = wrappers.indexOf(selectWrapper);
                                        if (idx !== -1 && wrappers[idx + 1]) {
                                            let nextInput = wrappers[idx + 1].querySelector("input, [role=\"combobox\"]");
                                            if (nextInput) {
                                                nextInput.focus();
                                            }
                                        }
                                    }
                                }
                            })(event)
                        ',
                        'x-on:input' => '
                            (function(e) {
                                let target = e.target;
                                if (!target) return;
                                
                                let classes = ["so-weight-input-column", "so-price-input-column", "so-discount-input-column"];
                                let foundClass = classes.find(c => target.classList.contains(c));
                                
                                if (foundClass) {
                                    let digits = target.value.replace(/[^0-9]/g, "");
                                    let formatted = digits;
                                    if (foundClass !== "so-discount-input-column" && digits !== "") {
                                        formatted = new Intl.NumberFormat("de-DE").format(parseInt(digits, 10));
                                    }
                                    if (target.value !== formatted) {
                                        let selectionStart = target.selectionStart;
                                        let selectionEnd = target.selectionEnd;
                                        let originalLength = target.value.length;
                                        
                                        target.value = formatted;
                                        
                                        let diff = formatted.length - originalLength;
                                        target.setSelectionRange(selectionStart + diff, selectionEnd + diff);
                                        
                                        target.dispatchEvent(new Event("input", { bubbles: true }));
                                    }
                                }
                            })(event)
                        '
                    ])
                    ->headerActions([
                        Forms\Components\Actions\Action::make('add_products')
                            ->label(__('Add Products'))
                            ->icon('heroicon-m-plus')
                            ->form([
                                Forms\Components\Select::make('product_ids')
                                    ->label(__('Select Products'))
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->extraAttributes([
                                        'style' => 'margin-bottom: 240px;',
                                    ])
                                    ->options(function (Get $get, $livewire) {
                                        $selectedIds = collect($livewire->data['items'] ?? $get('../../items') ?? [])
                                            ->pluck('product_id')
                                            ->filter()
                                            ->toArray();
                                        return Product::whereNotIn('id', $selectedIds)->pluck('name', 'id');
                                    })
                                    ->required(),
                            ])
                            ->action(function (array $data, Get $get, Set $set, $livewire) {
                                $customerId = $livewire->data['customer_id'] ?? $get('../../customer_id') ?? null;
                                $selectedProductIds = $data['product_ids'] ?? [];
                                $currentItems = $livewire->data['items'] ?? $get('../../items') ?? [];
                                
                                foreach ($selectedProductIds as $productId) {
                                    $existingKey = null;
                                    foreach ($currentItems as $key => $item) {
                                        if (($item['product_id'] ?? null) == $productId) {
                                            $existingKey = $key;
                                            break;
                                        }
                                    }

                                    $price = static::calculateProductPrice($customerId, $productId);

                                    if ($existingKey !== null) {
                                        $currentItems[$existingKey]['price'] = number_format($price, 0, '', '.');
                                    } else {
                                        $newKey = 'item_' . \Illuminate\Support\Str::random(12);
                                        $currentItems[$newKey] = [
                                            'product_id' => $productId,
                                            'weight' => 0,
                                            'price' => number_format($price, 0, '', '.'),
                                            'discount' => 0,
                                            'note' => '',
                                        ];
                                    }
                                }
                                $set('items', $currentItems);
                            })
                    ])
                    ->schema([
                        // Clean Repeater Header UI
                        Forms\Components\Grid::make(12)
                            ->schema([
                                Forms\Components\Placeholder::make('col_produk')->label(__('Product'))->columnSpan(3),
                                Forms\Components\Placeholder::make('col_berat')->label(__('Weight/Qty'))->columnSpan(2),
                                Forms\Components\Placeholder::make('col_harga')->label(__('Price'))->columnSpan(2),
                                Forms\Components\Placeholder::make('col_diskon')->label(__('Discount (%)'))->columnSpan(2),
                                Forms\Components\Placeholder::make('col_note')->label(__('Note'))->columnSpan(3),
                            ]),

                        Forms\Components\Repeater::make('items')
                            ->hiddenLabel()
                            ->defaultItems(0) // Start empty, force use of modal
                            ->disableItemMovement()
                            ->disableItemCreation() // Disable standard "Add Item" to force modal usage
                            ->validationMessages([
                                'min' => __('Sales order cannot be created without any products.'),
                            ])
                            ->columns(12)
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->hiddenLabel()
                                    ->placeholder(__('Product'))
                                    ->options(fn() => \App\Models\Product::pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->extraAttributes([
                                        'class' => 'so-product-select-column',
                                    ])
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('weight')
                                    ->hiddenLabel()
                                    ->placeholder(__('Weight'))
                                    ->required()
                                    // Click auto select all block as requested
                                    ->extraInputAttributes([
                                        'class' => 'text-right so-weight-input-column',
                                        'onclick' => 'this.select()',
                                        'inputmode' => 'numeric',
                                    ])
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('price')
                                    ->hiddenLabel()
                                    ->placeholder(__('Price'))
                                    ->required()
                                    ->prefix('Rp')
                                    ->extraInputAttributes([
                                        'class' => 'text-right so-price-input-column',
                                        'onclick' => 'this.select()',
                                        'inputmode' => 'numeric',
                                    ])
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('discount')
                                    ->hiddenLabel()
                                    ->placeholder(__('Discount'))
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->extraInputAttributes([
                                        'class' => 'text-right so-discount-input-column',
                                        'onclick' => 'this.select()',
                                        'inputmode' => 'numeric',
                                    ])
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('note')
                                    ->hiddenLabel()
                                    ->placeholder(__('Note'))
                                    ->maxLength(255)
                                    ->extraInputAttributes([
                                        'class' => 'so-note-input-column',
                                    ])
                                    ->columnSpan(3),
                            ]),
                    ])
            ]);
    }

    protected static function calculateProductPrice($customerId, $productId): int
    {
        \Illuminate\Support\Facades\Log::info("calculateProductPrice enter: customerId=" . var_export($customerId, true) . " productId=" . var_export($productId, true));
        if (!$customerId || !$productId) {
            return 0;
        }

        $customer = Customer::find($customerId);
        if (!$customer) {
            \Illuminate\Support\Facades\Log::info("calculateProductPrice: customer not found");
            return 0;
        }
        if (!$customer->customer_group_id) {
            \Illuminate\Support\Facades\Log::info("calculateProductPrice: customer has no group");
            return 0;
        }

        $priceList = PriceList::where('customer_group_id', $customer->customer_group_id)->first();
        if (!$priceList) {
            \Illuminate\Support\Facades\Log::info("calculateProductPrice: pricelist not found for group " . $customer->customer_group_id);
            return 0;
        }

        $priceItem = PriceListItem::where('price_list_id', $priceList->id)
            ->where('product_id', $productId)
            ->first();
        if ($priceItem) {
            \Illuminate\Support\Facades\Log::info("calculateProductPrice: found price=" . $priceItem->price);
            return $priceItem->price;
        }

        \Illuminate\Support\Facades\Log::info("calculateProductPrice: price item not found for product " . $productId);
        return 0;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('so_number')
                    ->label(__('SO Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_date')
                    ->label(__('Delivery Date'))
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('po_number')
                    ->label(__('PO Number'))
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('note')
                    ->label(__('Note'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->colors([
                        'gray' => 'waiting',
                        'info' => 'processing',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn(string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Created By'))
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()->hasPermission('view_deleted_sales_orders')),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->label(__('Customer'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('delivery_date')
                    ->form([
                        Forms\Components\DatePicker::make('delivery_from')
                            ->label(__('From Date')),
                        Forms\Components\DatePicker::make('delivery_until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['delivery_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['delivery_until'] ?? SalesOrder::max('delivery_date') ?? now()->toDateString();

                        return $query
                            ->when(
                                $from,
                                fn(Builder $query, $date): Builder => $query->whereDate('delivery_date', '>=', $date),
                            )
                            ->when(
                                $until,
                                fn(Builder $query, $date): Builder => $query->whereDate('delivery_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['delivery_from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['delivery_from'])->format('d M Y');
                        }
                        if ($data['delivery_until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['delivery_until'])->format('d M Y');
                        }
                        return $indicators;
                    }),
            ])
            ->recordClasses(fn (SalesOrder $record) => match (true) {
                $record->trashed() => 'bg-danger-50 dark:bg-danger-900/20',
                default => null,
            })
            ->headerActions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ExportAction::make('excel')
                        ->label('Excel')
                        ->icon('heroicon-o-document-text')
                        ->exporter(\App\Filament\Exports\SalesOrderExporter::class)
                        ->formats([\Filament\Actions\Exports\Enums\ExportFormat::Xlsx]),
                    Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.sales-orders-pdf', [
                                'records' => $records
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'sales-orders.pdf');
                        }),
                ])
                ->label('Export Data')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->actions([
                // Clickable rows handle editing; no action buttons displayed on the index table.
            ])
            ->recordUrl(fn(SalesOrder $record) => ($record->status === 'waiting' && !$record->trashed()) ? Pages\EditSalesOrder::getUrl([$record->id]) : null)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesOrders::route('/'),
            'create' => Pages\CreateSalesOrder::route('/create'),
            'edit' => Pages\EditSalesOrder::route('/{record}/edit'),
            'detail-list' => Pages\SalesOrderDetailList::route('/detail-list'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
