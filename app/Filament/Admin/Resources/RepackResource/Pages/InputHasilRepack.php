<?php

namespace App\Filament\Admin\Resources\RepackResource\Pages;

use App\Filament\Admin\Resources\RepackResource;
use App\Models\Repack;
use App\Models\RepackResult;
use App\Models\BeefStock;
use App\Models\BeefStockMovement;
use App\Models\Product;
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

class InputHasilRepack extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string $resource = RepackResource::class;
    
    protected static string $view = 'filament.resources.repack-resource.pages.input-hasil-repack';

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    public function getHeading(): string
    {
        return '';
    }

    public Repack $record;
    public ?array $data = [];

    public function mount(Repack $record): void
    {
        $this->record = $record;

        $defaultPackDate = now()->format('Y-m-d');
        $defaultGrade = 1;

        /* Inisialisasi nilai awal untuk form */
        $this->form->fill([
            'origin' => session('last_origin_' . $this->record->id, '2'),
            'warehouse_id' => session('last_warehouse_' . $this->record->id, 1),
            'pack_date' => $defaultPackDate,
            'grade_id' => $defaultGrade,
            'ph_level' => session('last_ph_' . $this->record->id),
            'show_exp' => session('show_exp_session', false),
            'exp_date' => Carbon::parse($defaultPackDate)->addMonths(3)->format('Y-m-d'),
        ]);
    }

    /* Menghitung tanggal kedaluwarsa berdasarkan grade_id */
    public static function calculateExpiry($packDate, $gradeId, callable $set)
    {
        if (!$packDate || !$gradeId) return;

        $date = Carbon::parse($packDate);

        // Jika grade ID = 1 (CHILL), exp 3 bulan, selain itu 1 tahun
        if ((int)$gradeId === 1) {
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
                            ->options(\App\Models\Warehouse::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->extraAttributes(['tabindex' => '-1'])
                            ->extraInputAttributes(['tabindex' => '-1']),

                        Forms\Components\Select::make('origin')
                            ->hiddenLabel()
                            ->placeholder(__('Pick the repack it came from'))
                            ->options([
                                '2' => 'Repack Stock',
                                '3' => 'Repack Import',
                                '4' => 'Repack Return',
                                '5' => 'Repack Trading',
                            ])
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
                            ->label(__('Show the expiry date on the label?'))
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
                                 * Tanpa komponen angka bawaan. Rentang pH cuma
                                 * 5,4-5,7 dengan langkah 0,1, jadi satu
                                 * sentuhan panah menggeser nilainya tanpa
                                 * terasa -- dan pH ikut masuk ke barcode 26
                                 * karakter, sehingga digit yang salah berarti
                                 * barcode yang salah arti. Sama seperti di
                                 * Boning.
                                 */
                                ->extraInputAttributes(['inputmode' => 'decimal'])
                                ->rules(['numeric', 'min:5.4', 'max:5.7'])
                                ->validationMessages([
                                    'min' => __('pH must be between :min and :max.', ['min' => '5.4', 'max' => '5.7']),
                                    'max' => __('pH must be between :min and :max.', ['min' => '5.4', 'max' => '5.7']),
                                ])
                                ->placeholder(__('PH (5.4 - 5.7)'))
                                ->extraInputAttributes([
                                    'tabindex' => '4',
                                    'onkeydown' => "if(event.key === ','){ event.preventDefault(); this.value = this.value + '.'; } else if(event.key === 'Enter'){ event.preventDefault(); document.getElementById('submit_btn_label').click(); }"
                                ]),
                        ]),
                    ])->columns(1)
            ])
            ->disabled(fn () => $this->record->kunci == 1)
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(RepackResult::query()->where('repack_id', $this->record->id))
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50, 'all'])
            ->columns([
                Tables\Columns\TextColumn::make('barcode')
                    ->label(__('Barcode'))
                    ->weight('bold')
                    ->size('sm')
                    ->alignCenter()
                    ->searchable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->size('sm')
                    ->weight('bold')
                    ->color('primary')
                    ->url(fn($record) => route('repack.label', ['id' => $record->id, 'show_exp' => 1]))
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('weight')
                    ->label(__('Qty'))
                    ->size('sm')
                    ->alignCenter()
                    ->formatStateUsing(fn($state) => number_format((float) $state, 2, '.', '')),

                Tables\Columns\TextColumn::make('grade.name')
                    ->label(__('Grade'))
                    ->alignCenter()
                    ->badge()
                    ->color(fn($state) => str_contains(strtolower($state), 'chill') ? 'info' : 'danger'),

                Tables\Columns\TextColumn::make('qty_pcs')
                    ->label(__('Pcs'))
                    ->size('sm')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Time'))
                    ->time('H:i:s')
                    ->size('sm')
                    ->alignCenter(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->tooltip(__('Delete data'))
                    ->requiresConfirmation()
                    ->hidden(fn () => $this->record->kunci == 1)
                    ->action(function ($record, $livewire) {
                        DB::transaction(function () use ($record) {
                            /* 1. Mencatat pengurangan stok dari hasil produksi yang dibatalkan */
                            BeefStockMovement::create([
                                'product_id' => $record->product_id,
                                'warehouse_id' => $record->warehouse_id ?? 1,
                                'condition' => $record->grade_id,
                                'barcode' => $record->barcode,
                                'transaction_type' => 'VOID_IN_REPACK',
                                'reference_document' => $record->repack->doc_no ?? 'DELETED',
                                'weight_in' => -$record->weight, // Nilai minus karena batal masuk
                                'pcs_in' => -$record->qty_pcs, // Nilai minus karena batal masuk
                                'created_by' => Auth::id(),
                            ]);

                            /* 2. Menghapus data stok hasil dari gudang dan rekaman hasil produksi */
                            BeefStock::where('barcode', $record->barcode)->delete();
                            $record->delete();
                        });

                        Notification::make()
                            ->title(__('The output has been deleted'))
                            ->success()
                            ->send();

                        /* Memanggil event livewire untuk memfokuskan kembali inputan */
                        $livewire->dispatch('refreshTable');
                    }),
            ]);
    }

    public function create(): void
    {
        if ($this->record->kunci == 1) {
            Notification::make()
                ->title(__('Failed'))
                ->body(__('This repack is locked.'))
                ->danger()
                ->send();
            return;
        }

        $showExp = $this->data['show_exp'] ?? false;
        $formData = $this->form->getState();

        /* Menyimpan state form ke session */
        session(['show_exp_session' => $showExp]);
        session(['last_origin_' . $this->record->id => $formData['origin']]);
        session(['last_warehouse_' . $this->record->id => $formData['warehouse_id']]);

        if (isset($formData['ph_level'])) {
            session(['last_ph_' . $this->record->id => $formData['ph_level']]);
        }

        $combinedInput = $formData['qty_pcs_combined'];
        $parts = explode('/', $combinedInput);
        $weight = (float) trim($parts[0]);
        $pcs = isset($parts[1]) && trim($parts[1]) !== '' ? (int) trim($parts[1]) : 1;

        try {
            $insertedItem = DB::transaction(function () use ($formData, $weight, $pcs) {

                /* Proses pembuatan kode barcode */
                $origin = $formData['origin'];
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
                $latestItem = RepackResult::withTrashed()->where('barcode', 'like', $prefix . '%')->lockForUpdate()->orderBy('id', 'desc')->first();
                $counter = ($latestItem && strlen($latestItem->barcode) >= 26) ? ((int) substr($latestItem->barcode, -4) + 1) : 1;
                $counterStr = str_pad($counter, 4, '0', STR_PAD_LEFT);

                $barcode = $origin . $dateStr . $productCode . $gradeId . $weightStr . $pcsStr . $phStr . $counterStr;

                /* Menyimpan data hasil repack */
                $item = RepackResult::create([
                    'repack_id' => $this->record->id,
                    'product_id' => $formData['product_id'],
                    'warehouse_id' => $formData['warehouse_id'],
                    'grade_id' => $formData['grade_id'],
                    'weight' => $weight,
                    'qty_pcs' => $pcs,
                    'ph_level' => $formData['ph_level'] ?? null,
                    'pack_date' => $formData['pack_date'],
                    'exp_date' => $formData['exp_date'],
                    'barcode' => $barcode,
                ]);

                /* Memasukkan barang hasil repack ke dalam stok */
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

                /* Mencatat pergerakan stok masuk */
                BeefStockMovement::create([
                    'product_id' => $formData['product_id'],
                    'warehouse_id' => $formData['warehouse_id'],
                    'condition' => $formData['grade_id'],
                    'barcode' => $barcode,
                    'transaction_type' => 'IN_REPACK',
                    'reference_document' => $this->record->doc_no,
                    'weight_in' => $weight,
                    'pcs_in' => $pcs,
                    'created_by' => Auth::id(),
                ]);

                return $item;
            });

            Notification::make()->title(__('Successfully Added'))->success()->send();

            /* Mengembalikan state form setelah sukses input */
            $this->form->fill([
                'warehouse_id' => $formData['warehouse_id'],
                'origin' => $formData['origin'],
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
                // Memicu cetak label
                $printUrl = route('repack.label', [
                    'id' => $insertedItem->id,
                    'show_exp' => $showExp ? 1 : 0
                ]);
                $this->dispatch('auto-print', url: $printUrl);
            }
        } catch (\Exception $e) {
            if (app()->runningUnitTests()) {
                throw $e;
            }
            Notification::make()->title(__('Error!'))->body($e->getMessage())->danger()->send();
        }
    }
}
