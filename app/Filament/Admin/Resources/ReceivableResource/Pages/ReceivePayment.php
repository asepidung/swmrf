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

    public function mount(int|string $record): void
    {
        abort_unless(static::canAccess(), 403);

        $this->record = CustomerGroup::findOrFail($record);

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

    public function form(Form $form): Form
    {
        $invoiceFields = [];
        foreach ($this->getOutstandingInvoices() as $inv) {
            $balanceStr = number_format($inv->balance, 0, ',', '.');
            $invoiceFields[] = TextInput::make("allocations.{$inv->id}")
                ->label("{$inv->invoice_number} | Sisa Piutang: Rp {$balanceStr}")
                ->numeric()
                ->prefix('Rp')
                ->default(0)
                ->maxValue($inv->balance);
        }

        return $form
            ->schema([
                Section::make(__('Data Pembayaran (Mutasi Masuk)'))
                    ->schema([
                        Select::make('bank_account_id')
                            ->label(__('Masuk ke Rekening'))
                            ->options(BankAccount::where('is_active', true)->pluck('initial', 'id'))
                            ->required()
                            ->searchable()
                            ->autofocus(),
                        DatePicker::make('payment_date')
                            ->label(__('Tanggal Bayar'))
                            ->required(),
                        TextInput::make('amount')
                            ->label(__('Nominal Uang Riil (Masuk Bank)'))
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(0),
                        TextInput::make('reference_number')
                            ->label(__('Bukti Transfer / Referensi'))
                            ->maxLength(255),
                        Textarea::make('note')
                            ->label(__('Catatan Khusus'))
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make(__('Potongan Administrasi / Promo (Deductions)'))
                    ->description(__('Masukkan jika pelanggan membayar tidak utuh karena ada potongan admin bank, klaim promo, dll.'))
                    ->schema([
                        Repeater::make('deductions')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('description')
                                    ->placeholder(__('Keterangan Potongan'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                TextInput::make('amount')
                                    ->placeholder(__('Nominal (Rp)'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->columnSpan(1),
                            ])
                            ->columns(3)
                            ->addActionLabel(__('Tambah Potongan'))
                            ->defaultItems(0)
                    ]),

                Section::make(__('Alokasi Pelunasan Invoice'))
                    ->description(__('Pecah total nominal uang + potongan ke masing-masing invoice di bawah ini.'))
                    ->schema(count($invoiceFields) > 0 ? $invoiceFields : [
                        \Filament\Forms\Components\Placeholder::make('no_invoice')
                            ->label('')
                            ->content(__('Tidak ada invoice yang perlu dilunasi untuk grup ini.')),
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
        return __('Receive Payment: ') . $this->record->name;
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
            Notification::make()->danger()->title('Nominal pembayaran atau potongan harus lebih dari 0.')->send();
            return;
        }

        if (abs($totalAvailable - $totalAllocated) > 0.01) {
            Notification::make()->danger()
                ->title('Alokasi Tidak Seimbang!')
                ->body("Total Dana (Rp " . number_format($totalAvailable, 0, ',', '.') . ") TIDAK SAMA dengan Total Dialokasikan (Rp " . number_format($totalAllocated, 0, ',', '.') . "). Selisih: Rp " . number_format(abs($totalAvailable - $totalAllocated), 0, ',', '.'))
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

            Notification::make()->success()->title('Pembayaran berhasil dicatat.')->send();
            
            $this->redirect(ReceivableResource::getUrl('view', ['record' => $this->record->id]));

        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->danger()->title('Terjadi kesalahan server.')->body($e->getMessage())->send();
        }
    }
}
