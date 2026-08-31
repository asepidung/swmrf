<?php

namespace App\Filament\Admin\Resources\BoningResource\Pages;

use App\Filament\Admin\Resources\BoningResource;
use App\Models\Boning;
use App\Models\BoningItem;
use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Grade;
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
use Illuminate\Support\Facades\Auth;
use Filament\Support\Enums\MaxWidth;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class LabelingBoning extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string $resource = BoningResource::class;
    protected static string $view = 'filament.resources.boning-resource.pages.labeling-boning';

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    public function getHeading(): string
    {
        return '';
    }

    public Boning $record;
    public ?array $data = [];

    public function mount(Boning $record): void
    {
        $this->record = $record;

        $defaultPackDate = now()->format('Y-m-d');
        $defaultGrade = 1;

        $this->form->fill([
            'pack_date' => $defaultPackDate,
            'warehouse_id' => 1,
            'grade_id' => $defaultGrade,
            'ph_level' => session('last_ph_' . $this->record->id),
            'show_exp' => session('show_exp_session', false),
            'exp_date' => Carbon::parse($defaultPackDate)->addMonths(3)->format('Y-m-d'),
        ]);
    }

    public function getProductionSummary()
    {
        return \App\Models\BoningItem::with('product')
            ->where('boning_id', $this->record->id)
            ->get()
            ->groupBy('product_id')
            ->map(function ($items) {
                return [
                    'product_name' => $items->first()->product->name ?? 'Unknown',
                    'box' => $items->count(),
                    'pcs' => $items->sum('qty_pcs'),
                    'qty' => $items->sum('weight'),
                ];
            })->sortBy('product_name');
    }

    public static function calculateExpiry($packDate, $gradeId, callable $set)
    {
        if (!$packDate || !$gradeId) return;

        $date = Carbon::parse($packDate);

        if (in_array((int)$gradeId, [1, 3])) { // CHILL or A
            $expiry = $date->addMonths(3)->format('Y-m-d');
        } else {
            $expiry = $date->addYear()->format('Y-m-d');
        }

        $set('exp_date', $expiry);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Select::make('warehouse_id')
                            ->hiddenLabel()
                            ->placeholder(__('Warehouse'))
                            ->options(Warehouse::pluck('name', 'id'))
                            ->required()
                            ->extraAttributes(['tabindex' => '-1'])
                            ->extraInputAttributes(['tabindex' => '-1']),

                        Forms\Components\Select::make('product_id')
                            ->hiddenLabel()
                            ->placeholder(__('Product'))
                            ->options(Product::orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->autofocus()
                            ->extraAttributes(['class' => 'product-select-container', 'tabindex' => '1'])
                            ->extraInputAttributes(['tabindex' => '1']),

                        Forms\Components\Select::make('grade_id')
                            ->hiddenLabel()
                            ->placeholder(__('Grade'))
                            ->options(Grade::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn($state, callable $set, callable $get) => self::calculateExpiry($get('pack_date'), $state, $set))
                            ->extraAttributes(['tabindex' => '3'])
                            ->extraInputAttributes(['tabindex' => '3']),

                        Forms\Components\DatePicker::make('pack_date')
                            ->hiddenLabel()
                            ->placeholder(__('Pack Date'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn($state, callable $set, callable $get) => self::calculateExpiry($state, $get('grade_id'), $set))
                            ->extraAttributes(['tabindex' => '-1'])
                            ->extraInputAttributes(['tabindex' => '-1']),

                        Forms\Components\Hidden::make('exp_date'),

                        Forms\Components\Checkbox::make('show_exp')
                            ->label(__('Show Expiry Date on Label'))
                            ->default(false)
                            ->dehydrated(false)
                            ->extraAttributes(['tabindex' => '-1']),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('qty_pcs_combined')
                                ->hiddenLabel()
                                ->placeholder(__('Weight/Pcs (e.g. 22.5/8)'))
                                ->required()
                                ->extraInputAttributes([
                                    'id' => 'qty_input_field',
                                    'tabindex' => '2',
                                    'class' => 'text-2xl font-black text-center text-primary-600',
                                    'oninput' => "this.value = this.value.replace(/,/g, '.');",
                                    'onkeydown' => "if(event.key === 'Enter') { event.preventDefault(); document.getElementById('submit_btn_label').click(); }"
                                ]),

                            Forms\Components\TextInput::make('ph_level')
                                ->hiddenLabel()
                                /*
                                 * Tanpa komponen angka bawaan. Rentang pH di
                                 * sini cuma 5,4-5,7 dengan langkah 0,1, jadi
                                 * SATU sentuhan panah menggeser nilainya tanpa
                                 * terasa -- dan pH ikut masuk ke barcode 26
                                 * karakter, sehingga digit yang salah berarti
                                 * barcode yang salah arti.
                                 */
                                ->extraInputAttributes(['inputmode' => 'decimal'])
                                ->rules(['numeric', 'min:5.4', 'max:5.7'])
                                ->validationMessages([
                                    'min' => __('pH must be between :min and :max.', ['min' => '5.4', 'max' => '5.7']),
                                    'max' => __('pH must be between :min and :max.', ['min' => '5.4', 'max' => '5.7']),
                                ])
                                ->placeholder(__('PH (5.4 - 5.7)'))
                                ->required()
                                ->extraInputAttributes([
                                    'tabindex' => '4',
                                    'onkeydown' => "if(event.key === ','){ event.preventDefault(); this.value = this.value + '.'; } else if(event.key === 'Enter'){ event.preventDefault(); document.getElementById('submit_btn_label').click(); }"
                                ]),
                        ]),
                    ])->columns(1)
            ])
            ->statePath('data');
    }

    public function exportExcel()
    {
        $summary = $this->getProductionSummary();

        $csvData = "Product,Box,Pcs,Qty (Kg)\n";
        $totalBox = 0;
        $totalPcs = 0;
        $totalQty = 0;

        foreach ($summary as $row) {
            $csvData .= "\"{$row['product_name']}\",{$row['box']},{$row['pcs']},{$row['qty']}\n";
            $totalBox += $row['box'];
            $totalPcs += $row['pcs'];
            $totalQty += $row['qty'];
        }

        $csvData .= "\"GRAND TOTAL\",{$totalBox},{$totalPcs},{$totalQty}\n";

        return response()->streamDownload(function () use ($csvData) {
            echo $csvData;
        }, 'Hasil_Produksi_' . $this->record->doc_no . '.csv');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(BoningItem::query()->where('boning_id', $this->record->id))
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50, 'all'])
            ->columns([
                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('Barcode'))
                    ->weight('bold')
                    ->size('sm')
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-sm font-bold text-center'])
                    ->searchable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->size('sm')
                    ->alignLeft()
                    ->extraHeaderAttributes(['class' => 'text-sm font-bold text-center'])
                    ->searchable()
                    ->weight('bold')
                    ->color('primary')
                    ->url(fn($record) => route('boning.label', ['id' => $record->id, 'show_exp' => 1]))
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('weight')
                    ->label(__('Weight'))
                    ->size('sm')
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-sm font-bold text-center'])
                    ->searchable()
                    ->formatStateUsing(fn($state) => number_format((float) $state, 2, '.', '')),

                Tables\Columns\TextColumn::make('grade.name')
                    ->label(__('Grade'))
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-sm font-bold text-center'])
                    ->badge()
                    ->color(fn($state) => in_array($state, ['CHILL', 'A']) ? 'info' : 'danger'),

                Tables\Columns\TextColumn::make('qty_pcs')
                    ->label(__('Pcs'))
                    ->size('sm')
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-sm font-bold text-center']),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Operator'))
                    ->size('sm')
                    ->alignLeft()
                    ->extraHeaderAttributes(['class' => 'text-sm font-bold text-center']),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Time'))
                    ->time('H:i:s')
                    ->size('sm')
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-sm font-bold text-center']),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_id')
                    ->label(__('Product'))
                    ->options(Product::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('grade_id')
                    ->label(__('Grade'))
                    ->options(Grade::where('is_active', true)->pluck('name', 'id'))
            ])
            ->actions([
                Tables\Actions\Action::make('repack_status')
                    ->label('R')
                    ->color('warning')
                    ->tooltip(__('Barang sudah masuk bahan repack'))
                    ->visible(fn (BoningItem $record) => DB::table('repack_materials')->where('barcode', $record->barcode)->exists())
                    ->extraAttributes([
                        'onclick' => 'event.preventDefault(); event.stopPropagation();',
                        'style' => 'cursor: not-allowed;',
                    ]),
                Tables\Actions\Action::make('tally_status')
                    ->label('D')
                    ->color('info')
                    ->tooltip(__('Barang sudah masuk tally'))
                    ->visible(fn (BoningItem $record) => DB::table('tally_items')->where('barcode', $record->barcode)->exists())
                    ->extraAttributes([
                        'onclick' => 'event.preventDefault(); event.stopPropagation();',
                        'style' => 'cursor: not-allowed;',
                    ]),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (BoningItem $record) => 
                        $this->record->kunci == 1 ||
                        DB::table('repack_materials')->where('barcode', $record->barcode)->exists() ||
                        DB::table('tally_items')->where('barcode', $record->barcode)->exists()
                    )
                    ->icon('heroicon-m-trash')
                    ->hiddenLabel()
                    ->color('danger')
                    ->tooltip(__('Delete Data'))
                    ->requiresConfirmation()
                    ->action(function (BoningItem $record) {
                        try {
                            DB::transaction(function () use ($record) {
                                // Cek pengaman ganda sebelum menghapus (TOCTOU Fixed)
                                if (DB::table('repack_materials')->where('barcode', $record->barcode)->lockForUpdate()->exists()) {
                                    throw new \Exception(__('Barang sudah digunakan di modul Repack.'));
                                }

                                if (DB::table('tally_items')->where('barcode', $record->barcode)->lockForUpdate()->exists()) {
                                    throw new \Exception(__('Barang sudah digunakan di modul Tally.'));
                                }

                                $stock = BeefStock::where('barcode', $record->barcode)->lockForUpdate()->first();

                                BeefStockMovement::create([
                                    'product_id' => $record->product_id,
                                    'warehouse_id' => $record->warehouse_id,
                                    'condition' => $record->grade_id,
                                    'barcode' => $record->barcode,
                                    'transaction_type' => 'VOID_BONING',
                                    'reference_document' => $record->boning->doc_no ?? 'DELETED',
                                    'weight_in' => -$record->weight,
                                    'pcs_in' => -$record->qty_pcs,
                                    'created_by' => Auth::id(),
                                ]);

                                if ($stock) {
                                    $stock->delete();
                                }
                                $record->delete();
                            });

                            Notification::make()
                                ->title(__('Data voided and removed from stock!'))
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title(__('Gagal!'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    public function create(): void
    {
        $showExp = $this->data['show_exp'] ?? false;
        $formData = $this->form->getState();

        session(['show_exp_session' => $showExp]);

        if (isset($formData['ph_level'])) {
            session(['last_ph_' . $this->record->id => $formData['ph_level']]);
        }

        $combinedInput = $formData['qty_pcs_combined'];
        $parts = explode('/', $combinedInput);
        $weight = (float) trim($parts[0]);
        $pcs = isset($parts[1]) && trim($parts[1]) !== '' ? (int) trim($parts[1]) : 1;

        try {
            $insertedItem = DB::transaction(function () use ($formData, $weight, $pcs) {
                $origin = '1';
                $dateStr = Carbon::parse($formData['pack_date'])->format('dmy');
                $product = Product::find($formData['product_id']);
                $productCode = $product->code ?? '000000';
                
                // standardise product code to length of 6 if shorter, otherwise leave as is
                if (strlen($productCode) > 6) {
                    $productCode = substr($productCode, 0, 6);
                } else {
                    $productCode = str_pad($productCode, 6, '0', STR_PAD_LEFT);
                }

                $gradeId = $formData['grade_id'];
                $weightStr = str_pad(round($weight * 100), 4, '0', STR_PAD_LEFT);
                $pcsStr = str_pad($pcs, 2, '0', STR_PAD_LEFT);
                $phStr = isset($formData['ph_level']) ? str_pad(round($formData['ph_level'] * 10), 2, '0', STR_PAD_LEFT) : '00';

                $prefix = $origin . $dateStr;
                $latestItem = BoningItem::withTrashed()->where('barcode', 'like', $prefix . '%')->lockForUpdate()->orderBy('id', 'desc')->first();
                $counter = ($latestItem && strlen($latestItem->barcode) >= 26) ? ((int) substr($latestItem->barcode, -4) + 1) : 1;
                $counterStr = str_pad($counter, 4, '0', STR_PAD_LEFT);

                $barcode = $origin . $dateStr . $productCode . $gradeId . $weightStr . $pcsStr . $phStr . $counterStr;

                $item = BoningItem::create([
                    'boning_id' => $this->record->id,
                    'product_id' => $formData['product_id'],
                    'warehouse_id' => $formData['warehouse_id'],
                    'grade_id' => $formData['grade_id'],
                    'weight' => $weight,
                    'qty_pcs' => $pcs,
                    'ph_level' => $formData['ph_level'] ?? null,
                    'pack_date' => $formData['pack_date'],
                    'exp_date' => $formData['exp_date'],
                    'barcode' => $barcode,
                    'created_by' => Auth::id(),
                ]);

                BeefStock::create([
                    'barcode' => $barcode,
                    'product_id' => $formData['product_id'],
                    'warehouse_id' => $formData['warehouse_id'],
                    'grade_id' => $formData['grade_id'],
                    'weight' => $weight,
                    'qty_pcs' => $pcs,
                    'ph_level' => $formData['ph_level'] ?? null,
                    'pack_date' => $formData['pack_date'],
                    'exp_date' => $formData['exp_date'],
                    'origin' => \App\Helpers\BarcodeHelper::getOrigin($barcode),
                    'status' => 'IN_STOCK',
                ]);

                BeefStockMovement::create([
                    'product_id' => $formData['product_id'],
                    'warehouse_id' => $formData['warehouse_id'],
                    'condition' => $formData['grade_id'],
                    'barcode' => $barcode,
                    'transaction_type' => 'IN_BONING',
                    'reference_document' => $this->record->doc_no,
                    'weight_in' => $weight,
                    'pcs_in' => $pcs,
                    'created_by' => Auth::id(),
                ]);

                return $item;
            });

            Notification::make()->title(__('Successfully Added'))->success()->send();

            $this->form->fill([
                'warehouse_id' => $formData['warehouse_id'],
                'product_id' => $formData['product_id'],
                'grade_id' => $formData['grade_id'],
                'pack_date' => $formData['pack_date'],
                'exp_date' => $formData['exp_date'],
                'ph_level' => $formData['ph_level'] ?? null,
                'qty_pcs_combined' => null,
                'show_exp' => session('show_exp_session'),
            ]);

            $this->dispatch('refreshTable');

            if ($insertedItem) {
                $printUrl = route('boning.label', [
                    'id' => $insertedItem->id,
                    'show_exp' => $showExp ? 1 : 0
                ]);
                $this->dispatch('auto-print', url: $printUrl);
            }
        } catch (\Exception $e) {
            Notification::make()->title(__('Error!'))->body($e->getMessage())->danger()->send();
        }
    }
}
