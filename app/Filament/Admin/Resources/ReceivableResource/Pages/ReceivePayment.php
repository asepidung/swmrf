<?php

namespace App\Filament\Admin\Resources\ReceivableResource\Pages;

use App\Filament\Admin\Resources\ReceivableResource;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\CustomerGroup;
use App\Models\Invoice;
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
            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
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
                            ->rules(['numeric', 'gte:0'])
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn () => $this->autoAllocate()),
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
                                // Kedua field ini sengaja dikirim saat kehilangan
                                // fokus, bukan ditunda sampai tombol disentuh.
                                //
                                // Dengan penundaan bawaan Livewire, nilai yang
                                // baru diketik masih menggantung saat barisnya
                                // dihapus -- lalu ikut terkirim pada permintaan
                                // BERIKUTNYA, dan Livewire MEMBUAT ULANG kunci
                                // baris yang sudah tidak ada. Itulah baris hantu
                                // yang muncul saat tombol simpan ditekan.
                                Select::make('type')
                                    ->placeholder(__('Deduction Type'))
                                    ->options(\App\Models\PaymentDeduction::typeOptions())
                                    ->default(\App\Models\PaymentDeduction::TYPE_BANK_FEE)
                                    ->required()
                                    ->columnSpan(2),

                                // Boleh dikosongkan, dan kosong itu BUKAN
                                // kelalaian melainkan pernyataan: potongan ini
                                // milik transfernya sendiri, bukan milik satu
                                // invoice. Itu perlakuan yang benar untuk biaya
                                // bank -- pelanggan bermaksud membayar penuh,
                                // banknya yang memotong di jalan.
                                //
                                // Diisi berarti sebaliknya: potongannya melekat
                                // pada invoice itu, dan tidak boleh mendarat di
                                // invoice lain hanya karena di situ uangnya
                                // kebetulan habis.
                                Select::make('invoice_id')
                                    ->placeholder(__('For all invoices'))
                                    ->options(fn (): array => $this->getOutstandingInvoices()
                                        ->mapWithKeys(fn ($invoice): array => [
                                            $invoice->id => $invoice->invoice_number,
                                        ])
                                        ->all())
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->autoAllocate())
                                    ->columnSpan(2),

                                TextInput::make('description')
                                    ->placeholder(__('Deduction Description'))
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->columnSpan(3),
                                $this->money('amount')
                                    ->hiddenLabel()
                                    ->placeholder(__('Amount (Rp)'))
                                    ->required()
                                    ->rules(['numeric', 'gt:0'])
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn () => $this->autoAllocate())
                                    ->columnSpan(2),
                            ])
                            ->columns(['default' => 1, 'lg' => 9])
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

    /**
     * Invoice yang masih menunggu dibayar, TERTUA LEBIH DULU.
     *
     * Urutannya dari TANGGAL INVOICE, bukan jatuh tempo. Keputusan Project
     * Owner, 4 September 2026, dan bedanya nyata: invoice tukar faktur yang
     * fakturnya belum ditukar belum punya jatuh tempo sama sekali, jadi kalau
     * diurutkan dari jatuh tempo ia akan tersingkir ke belakang -- padahal
     * justru dialah yang paling lama menunggu.
     */
    protected function getOutstandingInvoices()
    {
        return $this->record->receivables()
            ->with('invoice')
            ->get()
            ->pluck('invoice')
            ->filter(function ($invoice) {
                return $invoice && $invoice->status !== Invoice::STATUS_PAID && $invoice->balance > 0;
            })
            ->sortBy('invoice_date')
            ->values();
    }

    /**
     * Baca angka dari state form, yang masih membawa titik pemisah ribuan.
     *
     * `stripCharacters` baru bekerja saat penyimpanan dan validasi, sementara
     * di sini kita membaca `$this->data` mentah-mentah.
     */
    private function angka(mixed $nilai): float
    {
        return (float) str_replace('.', '', (string) ($nilai ?? '0'));
    }

    /**
     * Bagikan uang yang masuk ke invoice, TERTUA DULU.
     *
     * Hampir selalu ini yang dimaksud: lunasi yang paling lama menunggu, lalu
     * turun sampai uangnya habis. Yang benar-benar butuh keputusan manusia
     * cuma kasus khusus -- pelanggan sedang komplain satu invoice, atau
     * menyebut sendiri invoice mana yang ia bayar.
     *
     * Karena itu ini hanya MENGISIKAN, bukan mengunci. Setiap kotak tetap bisa
     * diubah sesudahnya, dan penjaga keseimbangannya tidak berubah sama
     * sekali: total alokasi tetap wajib sama dengan uang masuk ditambah
     * potongannya.
     *
     * Potongan ikut dihitung sebagai uang yang membayar, karena memang
     * begitu: tagihan yang dianggap lunas meski uangnya tidak pernah masuk.
     */
    /**
     * Potongan yang MENUNJUK satu invoice, dikelompokkan per invoice.
     *
     * @return array<int, float>
     */
    private function attachedDeductions(): array
    {
        $tertuju = [];

        foreach ($this->data['deductions'] ?? [] as $potongan) {
            if (! is_array($potongan)) {
                continue;
            }

            $invoiceId = $potongan['invoice_id'] ?? null;

            if (blank($invoiceId)) {
                continue;
            }

            $tertuju[(int) $invoiceId] = ($tertuju[(int) $invoiceId] ?? 0)
                + $this->angka($potongan['amount'] ?? 0);
        }

        return $tertuju;
    }

    /** Potongan yang TIDAK menunjuk invoice mana pun. */
    private function pooledDeductions(): float
    {
        $jumlah = 0.0;

        foreach ($this->data['deductions'] ?? [] as $potongan) {
            if (is_array($potongan) && blank($potongan['invoice_id'] ?? null)) {
                $jumlah += $this->angka($potongan['amount'] ?? 0);
            }
        }

        return $jumlah;
    }

    /**
     * Bagikan uang yang masuk ke invoice, TERTUA DULU.
     *
     * Hampir selalu ini yang dimaksud: lunasi yang paling lama menunggu, lalu
     * turun sampai uangnya habis. Yang benar-benar butuh keputusan manusia
     * cuma kasus khusus -- pelanggan sedang komplain satu invoice, atau
     * menyebut sendiri invoice mana yang ia bayar.
     *
     * Karena itu ini hanya MENGISIKAN, bukan mengunci. Setiap kotak tetap bisa
     * diubah sesudahnya, dan penjaga keseimbangannya tidak berubah sama
     * sekali: total alokasi tetap wajib sama dengan uang masuk ditambah
     * seluruh potongannya.
     *
     * POTONGAN YANG MENUNJUK INVOICE DIDAHULUKAN. Ia dipasang lebih dulu ke
     * invoicenya sendiri, baru sisa uangnya dibagi tertua-dulu. Tanpa langkah
     * itu, potongan promo untuk invoice tertentu akan larut dan mendarat di
     * invoice lain -- di mana pun uangnya kebetulan habis.
     *
     * Potongan yang TIDAK menunjuk invoice tetap dibagi bersama uang riilnya,
     * dan itu memang benar untuk biaya bank: pelanggan bermaksud membayar
     * penuh, banknya yang memotong di jalan.
     */
    public function autoAllocate(): void
    {
        $tertuju = $this->attachedDeductions();
        $kantong = $this->angka($this->data['amount'] ?? 0) + $this->pooledDeductions();

        $alokasi = [];

        foreach ($this->getOutstandingInvoices() as $invoice) {
            $sisa = (float) $invoice->balance;

            // Potongan yang memang ditujukan ke invoice ini dipasang lebih
            // dulu, dan tidak boleh melebihi tagihannya sendiri.
            $dariPotongan = min($tertuju[$invoice->id] ?? 0, $sisa);
            $sisa -= $dariPotongan;

            $dariKantong = max(min($kantong, $sisa), 0);
            $kantong -= $dariKantong;

            // Ditulis berpemisah ribuan supaya sama bentuknya dengan yang
            // diketik tangan; titiknya dibuang lagi saat disimpan.
            $alokasi[$invoice->id] = number_format($dariPotongan + $dariKantong, 0, ',', '.');
        }

        $this->data['allocations'] = $alokasi;
    }

    public function getTitle(): string
    {
        return __('Receive Payment').': '.$this->record->name;
    }

    /**
     * Buang baris potongan yang tidak pernah benar-benar diketik siapa pun.
     *
     * Dilaporkan Project Owner, 4 September 2026: menambah beberapa baris
     * potongan lalu menghapus semuanya membuat SATU baris hantu muncul saat
     * tombol simpan ditekan, lengkap dengan pesan kesalahan.
     *
     * Sebabnya di Livewire, bukan di sini. Nilai yang diketik dikirim dengan
     * penundaan; kalau barisnya dihapus sebelum nilainya sempat terkirim,
     * nilai itu ikut menumpang permintaan BERIKUTNYA -- dan menulis properti
     * bersarang ke kunci yang sudah tidak ada MEMBUAT ULANG kuncinya. Yang
     * lahir kembali bukan barisnya yang utuh, melainkan satu field saja.
     *
     * Itulah pembedanya, dan pembeda ini aman: baris yang benar-benar
     * ditambahkan SELALU punya kedua kuncinya sejak lahir --
     * `['description' => null, 'amount' => null]` -- sedangkan yang lahir dari
     * nilai menggantung hanya punya kunci yang kebetulan tertulis.
     *
     * Baris yang kosong tetapi utuh TIDAK dibuang: itu memang baris yang
     * ditambahkan lalu tidak diisi, dan pengguna berhak diberi tahu.
     */
    private function forgetGhostDeductionRows(): void
    {
        $rows = $this->data['deductions'] ?? null;

        if (! is_array($rows)) {
            return;
        }

        $this->data['deductions'] = array_filter(
            $rows,
            fn ($row): bool => is_array($row)
                && array_key_exists('description', $row)
                && array_key_exists('amount', $row),
        );
    }

    public function save(): void
    {
        $this->forgetGhostDeductionRows();

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

        // Potongan yang menunjuk sebuah invoice tidak boleh lebih besar
        // daripada tagihan invoice itu sendiri. Kalau dibiarkan, kelebihannya
        // akan diam-diam menutup tagihan invoice LAIN -- persis kekacauan yang
        // hendak dihindari dengan menunjuknya.
        foreach ($this->attachedDeductions() as $invoiceId => $jumlah) {
            $invoice = $this->getOutstandingInvoices()->firstWhere('id', $invoiceId);

            if ($invoice && $jumlah > (float) $invoice->balance + 0.01) {
                Notification::make()->danger()
                    ->title(__('Deduction is larger than the invoice it points to'))
                    ->body(__('Deduction for :invoice is Rp :deduction while its outstanding balance is only Rp :balance.', [
                        'invoice' => $invoice->invoice_number,
                        'deduction' => number_format($jumlah, 0, ',', '.'),
                        'balance' => number_format((float) $invoice->balance, 0, ',', '.'),
                    ]))
                    ->send();

                return;
            }
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
                if ((float) $deduction['amount'] > 0) {
                    $payment->deductions()->create([
                        'type' => $deduction['type'] ?? \App\Models\PaymentDeduction::TYPE_OTHER,
                        'invoice_id' => $deduction['invoice_id'] ?? null,
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
