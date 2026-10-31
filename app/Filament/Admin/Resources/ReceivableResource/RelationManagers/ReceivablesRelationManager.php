<?php

namespace App\Filament\Admin\Resources\ReceivableResource\RelationManagers;

use App\Models\Invoice;

use App\Models\Receivable;
use App\Filament\Admin\Resources\ReceivableResource;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReceivablesRelationManager extends RelationManager
{
    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Invoice Details (Receivables)');
    }

    protected static string $relationship = 'receivables';


    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('invoice_id')
            ->columns([
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label(__('Invoice Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Customer (Branch)'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice.invoice_date')
                    ->label(__('Invoice Date'))
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label(__('Due Date'))
                    ->getStateUsing(function (Receivable $record) {
                        $invoice = $record->invoice;
                        if (!$invoice) return '-';
                        
                        $tukarfaktur = $record->customer->invoice_exchange ?? false;
                        $status = $invoice->status;
                        $tgltf = $invoice->invoice_exchange_date;

                        if ($tukarfaktur && empty($tgltf) && $status === Invoice::STATUS_EXCHANGE_PENDING) {
                            return Invoice::statusLabel(Invoice::STATUS_EXCHANGE_PENDING);
                        }
                        
                        return $invoice->due_date ? $invoice->due_date->format('d M Y') : '-';
                    })
                    ->badge()
                    ->color(function (Receivable $record) {
                        $invoice = $record->invoice;
                        if (!$invoice || $invoice->status === Invoice::STATUS_PAID) return 'gray';
                        
                        $tukarfaktur = $record->customer->invoice_exchange ?? false;
                        $tgltf = $invoice->invoice_exchange_date;
                        if ($tukarfaktur && empty($tgltf) && $invoice->status === Invoice::STATUS_EXCHANGE_PENDING) {
                            return 'danger';
                        }
                        
                        if (!$invoice->due_date) return 'gray';

                        $today = now()->startOfDay();
                        $due = \Carbon\Carbon::parse($invoice->due_date)->startOfDay();
                        $diff = $today->diffInDays($due, false);

                        if ($diff < 0) {
                            return 'danger';
                        } elseif ($diff === 0) {
                            return 'warning';
                        }
                        return 'success';
                    })
                    ->tooltip(function (Receivable $record): ?string {
                        $invoice = $record->invoice;
                        if (!$invoice || !$invoice->due_date || $invoice->status === Invoice::STATUS_PAID) return null;
                        
                        $tukarfaktur = $record->customer->invoice_exchange ?? false;
                        $tgltf = $invoice->invoice_exchange_date;
                        if ($tukarfaktur && empty($tgltf) && $invoice->status === Invoice::STATUS_EXCHANGE_PENDING) {
                            return __('Not Yet Exchanged');
                        }

                        $today = now()->startOfDay();
                        $due = \Carbon\Carbon::parse($invoice->due_date)->startOfDay();
                        $diff = $today->diffInDays($due, false);
                        
                        if ($diff < 0) {
                            return __('Overdue by :days days', ['days' => abs($diff)]);
                        } elseif ($diff === 0) {
                            return __('Due today');
                        } else {
                            return __('Remaining :days days', ['days' => $diff]);
                        }
                    }),

                Tables\Columns\TextColumn::make('invoice.balance')
                    ->label(__('Outstanding Balance'))
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('invoice.status')
                    ->label(__('Status'))
                    ->badge()
                    ->colors([
                        'danger' => Invoice::STATUS_EXCHANGE_PENDING,
                        'success' => Invoice::STATUS_EXCHANGED,
                        'primary' => Invoice::STATUS_PAID,
                        'gray' => '-',
                    ])
                    ->formatStateUsing(fn ($state) => Invoice::statusLabel($state)),

                // Retur dan buka-kembali ada di Piutang, bukan di daftar Invoice (keputusan Ayah, 31 Oktober 2026): penanda ini
                // supaya sisa tagihan yang berkurang tidak terbaca sebagai galat oleh siapa pun yang membuka tabel ini.
                Tables\Columns\TextColumn::make('has_sales_returns')
                    ->label(__('Returns'))
                    ->getStateUsing(fn (Receivable $record): ?string => $record->invoice?->returnedAmount() > 0 ? __('Reduced by Return') : null)
                    ->badge()
                    ->colors(['warning' => __('Reduced by Return')]),
            ])
            ->filters([
                // Simple filters can be added here if needed
            ])
            ->headerActions([
                // Remove create action
            ])
            ->actions([
                // Membuka kembali invoice yang tadinya dianggap lunas (mis. hasil impor riwayat) tetapi sebenarnya belum selesai.
                // Dulu ada di daftar Invoice; dipindah ke sini karena ini urusan piutang (Ayah, 31 Oktober 2026).
                Tables\Actions\Action::make('reopen_settled')
                    ->label(__('Reopen'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->modalHeading(__('Reopen a settled invoice'))
                    ->modalDescription(__('Use this when a transaction that was counted as settled is actually not finished yet.'))
                    ->visible(fn (Receivable $record): bool => $record->invoice !== null
                        && $record->invoice->status === Invoice::STATUS_PAID
                        && ! $record->invoice->trashed()
                        && (auth()->user()?->hasPermission('reopen_settled_invoices') ?? false))
                    ->authorize(fn (): bool => auth()->user()?->hasPermission('reopen_settled_invoices') ?? false)
                    ->form(fn (Receivable $record): array => [
                        Forms\Components\TextInput::make('outstanding')
                            ->label(__('Amount still unpaid'))
                            ->helperText(__('Bill: :bill', ['bill' => number_format($record->invoice->billedAmount(), 0, ',', '.')]))
                            ->numeric()
                            ->required()
                            ->default($record->invoice->billedAmount())
                            ->minValue(1)
                            ->maxValue($record->invoice->billedAmount()),
                        Forms\Components\Textarea::make('reason')
                            ->label(__('Reason'))
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (Receivable $record, array $data): void {
                        \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data): void {
                            $locked = Invoice::whereKey($record->invoice_id)->lockForUpdate()->firstOrFail();
                            $locked->reopenSettled((float) $data['outstanding'], (string) $data['reason']);
                        });

                        Notification::make()->title(__('Invoice reopened'))->success()->send();
                    }),
            ])
            // Pilih invoice yang dibayar langsung di tabel (Owner, 23 Oktober 2026): terlama paling atas, yang lunas di bawah dan tidak bisa
            // dicentang. "Terima Pembayaran" lalu hanya menghitung invoice yang dicentang, jadi tidak ada pilihan ganda di halaman bayar.
            ->checkIfRecordIsSelectableUsing(fn (Receivable $record): bool => $record->invoice !== null
                && $record->invoice->status !== Invoice::STATUS_PAID
                && $record->invoice->balance > 0)
            ->header(fn (): \Illuminate\Contracts\View\View => view('filament.admin.resources.receivable-resource.selected-total', [
                'balances' => $this->getOwnerRecord()->receivables()->with('invoice')->get()
                    ->filter(fn (Receivable $r): bool => $r->invoice && $r->invoice->status !== Invoice::STATUS_PAID && $r->invoice->balance > 0)
                    ->mapWithKeys(fn (Receivable $r): array => [(string) $r->id => (float) $r->invoice->balance])
                    ->all(),
            ]))
            ->bulkActions([
                Tables\Actions\BulkAction::make('receive_payment')
                    ->label(__('Receive Payment'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (): bool => auth()->user()?->hasPermission('receive_receivables') ?? false)
                    ->authorize(fn (): bool => auth()->user()?->hasPermission('receive_receivables') ?? false)
                    ->deselectRecordsAfterCompletion(false)
                    ->action(function (\Illuminate\Support\Collection $records) {
                        $group = $this->getOwnerRecord();
                        $invoiceIds = $records->filter(fn (Receivable $r): bool => $r->invoice && $r->invoice->status !== Invoice::STATUS_PAID && $r->invoice->balance > 0)
                            ->map(fn (Receivable $r): int => (int) $r->invoice_id)->values();

                        if ($invoiceIds->isEmpty()) {
                            Notification::make()->warning()->title(__('Tick at least one invoice to pay.'))->send();

                            return;
                        }

                        // Grup yang anggotanya orang berbeda (KARYAWAN, WARGA +62): satu pembayaran hanya untuk satu customer.
                        if ($group->pays_per_customer && $records->pluck('customer_id')->unique()->count() > 1) {
                            Notification::make()->danger()
                                ->title(__('This group pays per customer: tick the invoices of one customer only.'))
                                ->send();

                            return;
                        }

                        return redirect(ReceivableResource::getUrl('payment', ['record' => $group->id, 'invoices' => $invoiceIds->implode(',')]));
                    }),
            ])
            ->defaultSort(fn (Builder $query) => $query
                ->orderByRaw('(select case when invoices.status = ? then 1 else 0 end from invoices where invoices.id = receivables.invoice_id) asc', [Invoice::STATUS_PAID])
                ->orderByRaw('(select invoices.invoice_date from invoices where invoices.id = receivables.invoice_id) asc')
                ->orderBy('receivables.id'));
    }
}
