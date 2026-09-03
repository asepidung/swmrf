<?php

namespace App\Filament\Admin\Resources\ReceivableResource\Pages;

use App\Filament\Admin\Resources\ReceivableResource;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\CustomerGroup;
use App\Models\Payment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Support\RawJs;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

class ReceivePayment extends Page
{
    protected static string $resource = ReceivableResource::class;

    protected static string $view = 'filament.admin.resources.receivable-resource.pages.receive-payment';

    public ?CustomerGroup $record = null;

    public ?array $data = [];

    /**
     * Halaman ini MENERIMA UANG, jadi dijaga di tingkat halaman.
     *
     * Menyembunyikan tombolnya saja tidak cukup: rutenya bisa dicapai dengan
     * mengetik URL langsung. Sebelumnya siapa pun yang bisa melihat piutang
     * otomatis bisa mencatat penerimaan pembayaran.
     */
    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->hasPermission('receive_receivables') ?? false;
    }

    /**
     * Filament SUDAH menyerahkan modelnya, bukan angka id.
     *
     * Sebelumnya tanda tangannya `mount(int|string $record)` dan isinya
     * `CustomerGroup::findOrFail($record)`. Padahal route resource sudah
     * mengubah `{record}` menjadi objek CustomerGroup sebelum mount berjalan,
     * jadi findOrFail mencari grup yang idnya berupa OBJEK -- tidak pernah
     * ketemu, dan ia menjawab dengan 404.
     *
     * Bentuk kegagalan yang paling menyesatkan yang bisa dipilih: Laravel
     * TIDAK mencatat 404 ke log (`ModelNotFoundException` ada di daftar
     * `dontReport` bawaan), sehingga di produksi halaman ini hanya menjawab
     * "404 Not Found" tanpa meninggalkan satu baris pun di Log Viewer. Terlihat
     * seperti halaman yang tidak ada, padahal kodenya berjalan dan datanya
     * lengkap.
     *
     * Tidak ada yang menahannya karena halaman ini -- yang MENERIMA UANG --
     * tidak punya satu pun pengujian, dan mencobanya dengan tangan menuntut
     * merangkai pelanggan sampai invoice lebih dulu.
     */
    public function mount(int|string|CustomerGroup $record): void
    {
        abort_unless(static::canAccess(), 403);

        $this->record = $record instanceof CustomerGroup
            ? $record
            : CustomerGroup::findOrFail($record);

        // Pre-fill allocations with 0 for all outstanding invoices
        $allocations = [];
        $outstandingInvoices = $this->getOutstandingInvoices();
        
        foreach ($outstandingInvoices as $inv) {
            $allocations[$inv->id] = 0;
        }

        $this->form->fill([
            'payment_date' => now()->toDateString(),
            'amount' => 0,
            'deductions' => [],
            'allocations' => $allocations,
        ]);
    }

    /**
     * Satu field uang, diformat sama di seluruh halaman ini.
     *
     * Halaman ini mengetik angka belasan juta, dan sampai 4 September 2026
     * setiap fieldnya masih `->numeric()` polos: bertombol panah, tanpa satu
     * pun pemisah ribuan. Alokasi 11.179.000 harus diketik tanpa penuntun,
     * lalu dicocokkan dengan mata melawan angka bertitik di labelnya.
     *
     * `mutateStateForValidation` di Filament ikut membuang titiknya, jadi
     * aturan seperti `maxValue()` tetap membandingkan angka -- bukan teks
     * bertitik.
     */
    private function money(string $name): TextInput
    {
        return TextInput::make($name)
            ->prefix('Rp')
            ->formatStateUsing(fn ($state): ?string => $state === null || $state === ''
                ? null
                : number_format((float) $state, 0, ',', '.'))
            ->mask(RawJs::make('$money($input, ',', '.', 0)'))
            ->stripCharacters('.')
            ->extraInputAttributes([
                'class' => 'text-right',
                'inputmode' => 'numeric',
                'x-on:focus' => '$el.select()',
            ]);
    }

    public function form(Form $form): Form
    {
        $invoiceFields = [];
        foreach ($this->getOutstandingInvoices() as $inv) {
            $balanceStr = number_format($inv->balance, 0, ',', '.');
            $invoiceFields[] = $this->money("allocations.{$inv->id}")
                ->label($inv->invoice_number.' | '.__('Outstanding').': Rp '.$balanceStr)
                ->default(0)
                ->rules(['numeric', 'gte:0'])
                ->maxValue($inv->balance);
        }

        return $form
            ->schema([
                Section::make(__('Payment Received'))
                    ->schema([
                        Select::make('bank_account_id')
                            ->label(__('Into Account'))
                            ->options(BankAccount::where('is_active', true)->pluck('initial', 'id'))
                            ->required()
                            ->searchable()
                            ->autofocus(),
                        DatePicker::make('payment_date')
                            ->label(__('Payment Date'))
                            ->required(),
                        $this->money('amount')
                            ->label(__('Amount Received in Bank'))
                            ->required()
                            ->default(0)
                            ->rules(['numeric', 'gte:0']),
                        TextInput::make('reference_number')
                            ->label(__('Transfer Reference'))
                            ->maxLength(255),
                        Textarea::make('note')
                            ->label(__('Note'))
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make(__('Deductions'))
                    ->description(__('Fill this in when the customer pays less than the full amount because of bank fees, promotion claims, and the like.'))
                    ->schema([
                        Repeater::make('deductions')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('description')
                                    ->placeholder(__('Deduction Description'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                $this->money('amount')
                                    ->hiddenLabel()
                                    ->placeholder(__('Amount (Rp)'))
                                    ->required()
                                    ->rules(['numeric', 'gt:0'])
                                    ->columnSpan(1),
                            ])
                            ->columns(3)
                            ->addActionLabel(__('Add Deduction'))
                            ->defaultItems(0)
                    ]),

                Section::make(__('Allocation to Invoices'))
                    ->description(__('Split the amount received plus its deductions across the invoices below.'))
                    // Total piutang grup ini ditampilkan lebih dulu.
                    //
                    // Tanpa angka ini, yang mencatat mengetik nominal tanpa
                    // pembanding apa pun: ia tahu sisa tiap invoice satu per
                    // satu, tetapi tidak tahu berapa seluruhnya. Padahal
                    // pertanyaan pertama saat uang masuk justru "ini melunasi
                    // semuanya atau sebagian?".
                    ->schema(count($invoiceFields) > 0 ? array_merge([
                        \Filament\Forms\Components\Placeholder::make('total_outstanding')
                            ->label(__('Total Outstanding'))
                            ->content(fn (): string => 'Rp '.number_format(
                                $this->getOutstandingInvoices()->sum('balance'),
                                0,
                                ',',
                                '.',
                            ))
                            ->extraAttributes(['class' => 'text-lg font-bold'])
                            ->columnSpanFull(),
                    ], $invoiceFields) : [
                        \Filament\Forms\Components\Placeholder::make('no_invoice')
                            ->label('')
                            ->content(__('This group has no invoice waiting to be paid.')),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    protected function getOutstandingInvoices()
    {
        return $this->record->receivables()
            ->with('invoice')
            ->get()
            ->pluck('invoice')
            ->filter(function ($invoice) {
                return $invoice && $invoice->status !== 'Lunas' && $invoice->balance > 0;
            });
    }

    public function getTitle(): string
    {
        return __('Receive Payment').': '.$this->record->name;
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $amountTransfer = (float) $data['amount'];
        $deductions = $data['deductions'] ?? [];
        $allocations = $data['allocations'] ?? [];

        $totalDeduction = 0;
        foreach ($deductions as $deduction) {
            $totalDeduction += (float) $deduction['amount'];
        }

        $totalAvailable = $amountTransfer + $totalDeduction;

        $totalAllocated = 0;
        foreach ($allocations as $invId => $allocAmount) {
            $totalAllocated += (float) $allocAmount;
        }

        if ($totalAvailable <= 0) {
            Notification::make()->danger()->title(__('The payment or its deductions must be more than zero.'))->send();
            return;
        }

        if (abs($totalAvailable - $totalAllocated) > 0.01) {
            Notification::make()->danger()
                ->title(__('Allocation does not balance'))
                ->body(__('Money received is Rp :available while Rp :allocated has been allocated. The difference is Rp :difference.', [
                    'available' => number_format($totalAvailable, 0, ',', '.'),
                    'allocated' => number_format($totalAllocated, 0, ',', '.'),
                    'difference' => number_format(abs($totalAvailable - $totalAllocated), 0, ',', '.'),
                ]))
                ->send();
            return;
        }

        DB::beginTransaction();
        try {
            // 1. Create Payment
            $payment = Payment::create([
                'customer_group_id' => $this->record->id,
                'bank_account_id' => $data['bank_account_id'],
                'payment_date' => $data['payment_date'],
                'amount' => $amountTransfer,
                'total_deduction' => $totalDeduction,
                'reference_number' => $data['reference_number'],
                'note' => $data['note'],
            ]);

            // 2. Insert Deductions
            foreach ($deductions as $deduction) {
                if ((float)$deduction['amount'] > 0) {
                    $payment->deductions()->create([
                        'description' => $deduction['description'],
                        'amount' => $deduction['amount'],
                    ]);
                }
            }

            // 3. Process Allocations & Update Invoices
            foreach ($allocations as $invId => $allocAmount) {
                $allocAmount = (float) $allocAmount;
                if ($allocAmount > 0) {
                    $payment->allocations()->create([
                        'invoice_id' => $invId,
                        'amount_allocated' => $allocAmount,
                    ]);

                    $invoice = \App\Models\Invoice::find($invId);

                    // Yang bertambah adalah jumlah yang SUDAH DIBAYAR, bukan
                    // sisa tagihannya. Sisa tagihan diturunkan sendiri oleh
                    // Invoice::recalculate(), dan hanya di sana.
                    //
                    // Dulu baris ini menimpa `balance` langsung. Kolom yang
                    // sama juga dihitung ulang oleh form Invoice dari barang
                    // dan uang muka, tanpa tahu apa-apa tentang pembayaran --
                    // jadi cukup menyunting invoicenya sekali dan pembayaran
                    // ini lenyap dari tagihan.
                    $invoice?->applyPayment($allocAmount);
                }
            }

            // 4. Update Bank Account Balance & Record Transaction
            $bankAccount = BankAccount::find($data['bank_account_id']);
            // Saldo TIDAK ditulis ke master data. Ia dihitung dari baris
            // buku kas di bawah ini -- lihat BankAccount::currentBalance().
            if ($bankAccount && $amountTransfer > 0) {
                BankTransaction::create([
                    'bank_account_id' => $bankAccount->id,
                    'type' => 'in', // Uang masuk
                    'amount' => $amountTransfer,
                    'reference_type' => Payment::class,
                    'reference_id' => $payment->id,
                    'description' => "Penerimaan Pembayaran Piutang Grup: {$this->record->name} (No: {$payment->payment_number})",
                    'transaction_date' => $data['payment_date'],
                ]);
            }

            DB::commit();

            Notification::make()->success()->title(__('Payment recorded'))->send();
            
            $this->redirect(ReceivableResource::getUrl('view', ['record' => $this->record->id]));

        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->danger()->title(__('Something went wrong.'))->body($e->getMessage())->send();
        }
    }
}
