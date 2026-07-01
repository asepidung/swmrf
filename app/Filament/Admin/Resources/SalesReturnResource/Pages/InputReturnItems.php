<?php

namespace App\Filament\Admin\Resources\SalesReturnResource\Pages;

use App\Filament\Admin\Resources\SalesReturnResource;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Product;
use App\Models\Grade;
use App\Models\TallyItem;
use Filament\Resources\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Facades\DB;
use Filament\Support\Enums\MaxWidth;
use Filament\Notifications\Notification;
use Carbon\Carbon;
use Filament\Actions;

class InputReturnItems extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string $resource = SalesReturnResource::class;
    
    protected static string $view = 'filament.resources.sales-return-resource.pages.input-return-items';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Lock')
                ->tooltip('Lock Return')
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->hiddenLabel()
                ->requiresConfirmation()
                ->hidden(fn () => $this->record->status !== 'Draft')
                ->action(function () {
                    $this->record->update(['status' => 'Approved']);
                    Notification::make()->title('Return Dikunci')->success()->send();
                    $this->redirect(SalesReturnResource::getUrl('edit', ['record' => $this->record]));
                }),
                
            Actions\Action::make('back')
                ->tooltip('Kembali')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->hiddenLabel()
                ->url(fn () => SalesReturnResource::getUrl('edit', ['record' => $this->record])),
        ];
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    public SalesReturn $record;
    public ?array $dataScan = [];
    public ?array $dataWeigh = [];
    public string $activeTab = 'scan';

    public function mount(SalesReturn $record): void
    {
        $this->record = $record;

        $defaultPackDate = now()->format('Y-m-d');
        
        $this->scanForm->fill([]);

        $this->weighForm->fill([
            'warehouse_id' => session('sr_warehouse_id_' . $this->record->id, 1),
            'product_id' => session('sr_product_id_' . $this->record->id),
            'grade_id' => session('sr_grade_id_' . $this->record->id, 1),
            'pack_date' => session('sr_pack_date_' . $this->record->id, $defaultPackDate),
            'exp_date' => session('sr_exp_date_' . $this->record->id, Carbon::parse($defaultPackDate)->addMonths(3)->format('Y-m-d')),
            'show_exp' => session('sr_show_exp_' . $this->record->id, true),
            'ph_level' => session('sr_ph_level_' . $this->record->id),
        ]);
    }

    protected function getForms(): array
    {
        return [
            'scanForm',
            'weighForm',
        ];
    }

    public function scanForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('barcode')
                    ->hiddenLabel()
                    ->placeholder(__('Scan Barcode Lama (Karton Utuh)'))
                    ->required()
                    ->autofocus()
                    ->extraInputAttributes([
                        'onkeydown' => "if(event.key === 'Enter') { event.preventDefault(); document.getElementById('submit_scan_btn').click(); }"
                    ]),
            ])
            ->statePath('dataScan');
    }

    public function weighForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Select::make('warehouse_id')
                        ->hiddenLabel()
                        ->placeholder(__('Warehouse'))
                        ->options(\App\Models\Warehouse::where('is_active', true)->pluck('name', 'id'))
                        ->required()
                        ->searchable(),

                    Forms\Components\Select::make('product_id')
                        ->hiddenLabel()
                        ->placeholder(__('Product'))
                        ->options(Product::orderBy('name')->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('grade_id')
                        ->hiddenLabel()
                        ->placeholder(__('Grade'))
                        ->options(Grade::where('is_active', true)->pluck('name', 'id'))
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn($state, callable $set, callable $get) => \App\Filament\Admin\Resources\RepackResource\Pages\InputHasilRepack::calculateExpiry($get('pack_date'), $state, $set))
                        ->extraAttributes(['tabindex' => '-1'])
                        ->extraInputAttributes(['tabindex' => '-1']),

                    Forms\Components\DatePicker::make('pack_date')
                        ->hiddenLabel()
                        ->placeholder(__('Pack Date'))
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn($state, callable $set, callable $get) => \App\Filament\Admin\Resources\RepackResource\Pages\InputHasilRepack::calculateExpiry($state, $get('grade_id'), $set))
                        ->extraAttributes(['tabindex' => '-1'])
                        ->extraInputAttributes(['tabindex' => '-1']),

                    Forms\Components\Hidden::make('exp_date'),

                    Forms\Components\Checkbox::make('show_exp')
                        ->label(__('Tampilkan Tanggal Expired Pada Label'))
                        ->default(true)
                        ->extraAttributes(['tabindex' => '-1']),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('qty_pcs_combined')
                            ->hiddenLabel()
                            ->placeholder(__('Weight/Pcs (e.g. 22.5/8)'))
                            ->required()
                            ->extraInputAttributes([
                                'id' => 'qty_input_field',
                                'class' => 'text-2xl font-black text-center text-primary-600',
                                'oninput' => "this.value = this.value.replace(/,/g, '.');",
                                'onkeydown' => "if(event.key === 'Enter') { event.preventDefault(); document.getElementById('submit_weigh_btn').click(); }"
                            ]),

                        Forms\Components\TextInput::make('ph_level')
                            ->hiddenLabel()
                            ->numeric()
                            ->step(0.1)
                            ->minValue(5.4)
                            ->maxValue(5.7)
                            ->placeholder(__('PH (5.4 - 5.7)'))
                            ->extraInputAttributes([
                                'onkeydown' => "if(event.key === ','){ event.preventDefault(); this.value = this.value + '.'; } else if(event.key === 'Enter'){ event.preventDefault(); document.getElementById('submit_weigh_btn').click(); }"
                            ]),
                    ]),
                ])->columns(1),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('submit_weigh')
                            ->label('PRINT & SAVE LABEL')
                            ->color('warning')
                            ->action('processWeigh')
                            ->extraAttributes(['class' => 'w-full'])
                    ])->fullWidth(),
                ]),
            ])
            ->statePath('dataWeigh');
    }

    public function processScan(): void
    {
        $formData = $this->scanForm->getState();
        $barcode = $formData['barcode'];

        try {
            $tallyItem = TallyItem::where('barcode', $barcode)->orderBy('id', 'desc')->first();
            
            if (!$tallyItem) {
                throw new \Exception('Barcode tidak ditemukan di sistem (Tally).');
            }
            
            $productId = $tallyItem->product_id;
            $gradeId = $tallyItem->grade_id;
            $weight = $tallyItem->weight;
            $pcs = $tallyItem->qty_pcs;
            $origin = $tallyItem->origin;

            if (SalesReturnItem::where('sales_return_id', $this->record->id)->where('barcode', $barcode)->exists()) {
                throw new \Exception('Barcode sudah di-scan di retur ini.');
            }

            SalesReturnItem::create([
                'sales_return_id' => $this->record->id,
                'product_id' => $productId,
                'warehouse_id' => 1,
                'grade_id' => $gradeId,
                'barcode' => $barcode,
                'weight' => $weight,
                'qty_pcs' => $pcs,
                'ph_level' => $tallyItem->ph_level ?? null,
                'pack_date' => $tallyItem->pack_date ?? null,
                'exp_date' => $tallyItem->exp_date ?? null,
                'origin' => $origin,
                'is_repacked' => false,
            ]);

            $this->scanForm->fill([]);
            Notification::make()->title('Sukses scan barcode!')->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
        }
    }

    public function processWeigh(): void
    {
        $formData = $this->weighForm->getState();
        
        try {
            $qtyInput = $formData['qty_pcs_combined'];
            $parts = explode('/', $qtyInput);
            
            $weight = (float) trim($parts[0]);
            $pcs = isset($parts[1]) ? (int) trim($parts[1]) : 1;

            if ($weight <= 0) {
                throw new \Exception('Berat tidak valid.');
            }

            DB::transaction(function () use ($formData, $weight, $pcs) {
                $origin = '4'; // Repack Return origin (per project rules)
                $dateStr = Carbon::parse($formData['pack_date'])->format('dmy');
                
                $product = Product::find($formData['product_id']);
                $productCode = $product->code ?? '000000';
                $gradeId = $formData['grade_id'];

                $weightStr = str_pad(number_format($weight, 2, '', ''), 5, '0', STR_PAD_LEFT);
                $pcsStr = str_pad($pcs, 2, '0', STR_PAD_LEFT);

                $phStr = '0000';
                if (!empty($formData['ph_level'])) {
                    $phStr = str_pad(number_format((float)$formData['ph_level'], 2, '', ''), 4, '0', STR_PAD_LEFT);
                }

                $prefix = $origin . $dateStr;
                $latestItem = SalesReturnItem::where('barcode', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
                $counter = ($latestItem && strlen($latestItem->barcode) >= 25) ? ((int) substr($latestItem->barcode, -3) + 1) : 1;
                $counterStr = str_pad($counter, 3, '0', STR_PAD_LEFT);

                $barcode = $origin . $dateStr . $productCode . $gradeId . $weightStr . $pcsStr . $phStr . $counterStr;

                $insertedItem = SalesReturnItem::create([
                    'sales_return_id' => $this->record->id,
                    'product_id' => $formData['product_id'],
                    'warehouse_id' => $formData['warehouse_id'] ?? 1,
                    'grade_id' => $gradeId,
                    'barcode' => $barcode,
                    'weight' => $weight,
                    'qty_pcs' => $pcs,
                    'ph_level' => $formData['ph_level'] ?? null,
                    'pack_date' => $formData['pack_date'],
                    'exp_date' => $formData['exp_date'],
                    'origin' => $origin,
                    'is_repacked' => true,
                ]);

                // Auto Print
                $showExp = $formData['show_exp'] ?? true;
                $printUrl = route('sales-return.label', [
                    'id' => $insertedItem->id,
                    'show_exp' => $showExp ? 1 : 0
                ]);
                $this->dispatch('auto-print', url: $printUrl);
            });

            // Set sessions
            session([
                'sr_warehouse_id_' . $this->record->id => $formData['warehouse_id'] ?? null,
                'sr_product_id_' . $this->record->id => $formData['product_id'] ?? null,
                'sr_grade_id_' . $this->record->id => $formData['grade_id'] ?? null,
                'sr_pack_date_' . $this->record->id => $formData['pack_date'] ?? null,
                'sr_exp_date_' . $this->record->id => $formData['exp_date'] ?? null,
                'sr_show_exp_' . $this->record->id => $showExp,
                'sr_ph_level_' . $this->record->id => $formData['ph_level'] ?? null,
            ]);

            $this->weighForm->fill([
                'warehouse_id' => $formData['warehouse_id'] ?? null,
                'product_id' => $formData['product_id'] ?? null,
                'grade_id' => $formData['grade_id'] ?? null,
                'pack_date' => $formData['pack_date'] ?? null,
                'exp_date' => $formData['exp_date'] ?? null,
                'show_exp' => $showExp,
                'ph_level' => $formData['ph_level'] ?? null,
            ]);
        } catch (\Exception $e) {
            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SalesReturnItem::where('sales_return_id', $this->record->id)->latest())
            ->columns([
                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('Barcode'))
                    ->weight('bold')
                    ->size('xs')
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->size('xs'),
                Tables\Columns\TextColumn::make('grade.name')
                    ->label(__('Grade'))
                    ->size('xs'),
                Tables\Columns\TextColumn::make('weight')
                    ->label(__('Weight/Pcs'))
                    ->size('xs')
                    ->formatStateUsing(fn ($record) => number_format($record->weight, 2) . '/' . $record->qty_pcs)
                    ->summarize(
                        Tables\Columns\Summarizers\Summarizer::make()
                            ->label('')
                            ->using(function ($query) {
                                $wt = (clone $query)->sum('weight');
                                $pcs = (clone $query)->sum('qty_pcs');
                                $val = number_format((float) $wt, 2) . ' / ' . $pcs;
                                return new \Illuminate\Support\HtmlString('<span style="color: #eab308; font-weight: bold; font-size: 0.875rem;">' . $val . '</span>');
                            })
                    ),
                Tables\Columns\TextColumn::make('ph_level')
                    ->label(__('pH'))
                    ->size('xs'),
                Tables\Columns\TextColumn::make('pack_date')
                    ->label(__('POD'))
                    ->date('d/m/Y')
                    ->size('xs'),
                Tables\Columns\TextColumn::make('origin')
                    ->label(__('Origin'))
                    ->alignCenter()
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record || !$record->barcode) return $state;
                        $prefix = substr($record->barcode, 0, 1);
                        $map = [
                            '1' => 'BNG',
                            '2' => 'RSTK',
                            '3' => 'RIMP',
                            '4' => 'RRTN',
                            '5' => 'RTRD',
                            '6' => 'RLBT',
                            '7' => 'TRDL',
                            '8' => 'TRDI',
                        ];
                        return $map[$prefix] ?? $state;
                    })
                    ->badge()
                    ->color('gray'),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Delete'),
            ]);
    }
}
