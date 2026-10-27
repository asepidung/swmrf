<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ExpenseResource\Pages;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\User;
use App\Support\TrashedRecords;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $cluster = \App\Filament\Clusters\ExpensesCluster::class;

    // Sub-menu cluster di atas, bukan di samping (posisi dibaca dari Resource, bukan dari Cluster).
    protected static \Filament\Pages\SubNavigationPosition $subNavigationPosition = \Filament\Pages\SubNavigationPosition::Top;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('Expense');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Expenses');
    }

    /**
     * Sama seperti InvoiceResource -- nilai kolom decimal(15,2) berbentuk
     * "1200000.00", dan mask $money membuang karakter non-digit sehingga
     * dua nol di belakang titik ikut terbaca sebagai digit tanpa
     * `formatStateUsing` di sini.
     */
    private static function money(string $name): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->prefix('Rp')
            ->formatStateUsing(fn ($state): ?string => $state === null || $state === ''
                ? null
                : number_format((float) $state, 0, ',', '.'))
            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
            ->stripCharacters('.')
            ->extraInputAttributes(['class' => 'text-right', 'inputmode' => 'numeric']);
    }

    /** Angka dari isian uang yang sedang diketik ("1.200.000" -> 1200000). */
    public static function moneyValue(mixed $state): float
    {
        return (float) str_replace(['.', ' '], '', (string) $state);
    }

    private static function canManage(): bool
    {
        return auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('edit_expenses') ?? false);
    }

    /**
     * Baris barang: kategori, keterangan yang diketik, total harga. Dipakai form buat dan modal laporan
     * balik kasbon (nilai disesuaikan dengan nota).
     */
    private static function itemsRepeater(): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make('items')
            ->label(__('Items'))
            ->schema([
                Forms\Components\Select::make('expense_category_id')
                    ->label(__('Category'))
                    ->options(fn () => ExpenseCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->columnSpan(['md' => 3]),
                Forms\Components\TextInput::make('description')
                    ->label(__('Description'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(['md' => 5]),
                static::money('amount')
                    ->label(__('Total Price'))
                    ->required()
                    ->live(onBlur: true)
                    ->columnSpan(['md' => 4]),
            ])
            ->columns(['md' => 12])
            ->defaultItems(1)
            ->minItems(1)
            ->addActionLabel(__('Add item'))
            ->live();
    }

    private static function itemsTotalPlaceholder(): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make('items_total')
            ->label(__('Total'))
            ->content(fn (Forms\Get $get): string => 'Rp '.number_format(
                collect($get('items') ?? [])->sum(fn ($item) => static::moneyValue($item['amount'] ?? 0)),
                0, ',', '.',
            ));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Expense'))
                    ->schema([
                        Forms\Components\DatePicker::make('expense_date')
                            ->label(__('Date'))
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('recipient_name')
                            ->label(__('Requester'))
                            ->required()
                            ->maxLength(255)
                            ->extraInputAttributes(['style' => 'text-transform:uppercase']),

                        Forms\Components\Select::make('user_id')
                            ->label(__('Requester Account (optional)'))
                            ->options(fn () => User::where('is_active', true)->pluck('name', 'id'))
                            ->searchable(),

                        Forms\Components\Select::make('bank_account_id')
                            ->label(__('Cash/Bank Account'))
                            ->options(fn () => BankAccount::where('is_active', true)->pluck('initial', 'id'))
                            ->default(fn () => BankAccount::cashAccount()->id)
                            ->searchable()
                            ->required()
                            ->disabled(fn (string $context): bool => $context === 'edit')
                            ->dehydrated(),
                    ])->columns(['md' => 4]),

                Forms\Components\Section::make(__('Items'))
                    ->schema([
                        static::itemsRepeater()
                            ->hiddenLabel()
                            // Barang menentukan BankTransaction yang lahir saat dibuat; sesudahnya hanya
                            // berubah lewat laporan balik kasbon (aksi Settle).
                            ->disabled(fn (string $context): bool => $context === 'edit')
                            ->dehydrated(fn (string $context): bool => $context === 'create'),
                        static::itemsTotalPlaceholder(),
                    ]),

                Forms\Components\Section::make(__('How is it paid?'))
                    ->schema([
                        Forms\Components\Radio::make('type')
                            ->hiddenLabel()
                            ->options([
                                Expense::TYPE_REIMBURSE => __('Reimburse'),
                                Expense::TYPE_ADVANCE => __('Advance (Kasbon)'),
                            ])
                            ->descriptions([
                                Expense::TYPE_REIMBURSE => __('The requester paid first; we pay them back according to the receipts.'),
                                Expense::TYPE_ADVANCE => __('We give the estimated money now; the requester reports back with receipts and the value is adjusted.'),
                            ])
                            ->required()
                            ->disabled(fn (string $context): bool => $context === 'edit')
                            ->dehydrated(),
                    ]),
            ]);
    }

    /** Aksi laporan balik kasbon: nilai tiap barang disesuaikan dengan nota yang dibawa pemohon. */
    public static function settleAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('settle')
            ->label(__('Report Back'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Expense $record): bool => $record->awaitingReport() && static::canManage())
            ->fillForm(fn (Expense $record): array => [
                'items' => $record->items->map(fn ($item) => [
                    'expense_category_id' => $item->expense_category_id,
                    'description' => $item->description,
                    'amount' => $item->amount,
                ])->all(),
            ])
            ->form([
                static::itemsRepeater()
                    ->helperText(__('Adjust each item to the receipts. Add or remove rows if the receipts differ.')),
                static::itemsTotalPlaceholder(),
                Forms\Components\FileUpload::make('receipt_photo')
                    ->label(__('Receipt Photo'))
                    ->disk('local')
                    ->directory('expense-receipts')
                    ->visibility('private')
                    ->image()
                    ->maxSize(5120),
            ])
            ->action(function (Expense $record, array $data): void {
                if (! static::canManage()) {
                    Notification::make()->title(__('You do not have permission to do this.'))->danger()->send();

                    return;
                }

                try {
                    // Foto disimpan SELAGI masih Open -- settle() bisa langsung mengunci baris ke
                    // Settled, dan update() sesudahnya akan ditolak guard `updating()` di model.
                    if (! empty($data['receipt_photo'])) {
                        $record->update(['receipt_photo' => $data['receipt_photo']]);
                    }

                    $record->settle($data['items']);
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                    return;
                }

                $owed = $record->fresh()->outstandingReturn();

                $owed > 0
                    ? Notification::make()->title(__('Reported. The requester still has to return :amount.', ['amount' => 'Rp '.number_format($owed, 0, ',', '.')]))->warning()->send()
                    : Notification::make()->title(__('Expense settled.'))->success()->send();
            });
    }

    /** Aksi terima pengembalian sisa kasbon -- sebagian atau bertahap. */
    public static function returnAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('receive_return')
            ->label(__('Receive Return'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->visible(fn (Expense $record): bool => $record->outstandingReturn() > 0 && static::canManage())
            ->fillForm(fn (Expense $record): array => ['amount' => $record->outstandingReturn()])
            ->form(fn (Expense $record): array => [
                Forms\Components\Placeholder::make('outstanding')
                    ->label(__('Still to be returned'))
                    ->content('Rp '.number_format($record->outstandingReturn(), 0, ',', '.')),
                static::money('amount')
                    ->label(__('Amount returned now'))
                    ->required(),
            ])
            ->action(function (Expense $record, array $data): void {
                if (! static::canManage()) {
                    Notification::make()->title(__('You do not have permission to do this.'))->danger()->send();

                    return;
                }

                try {
                    $record->receiveReturn((float) $data['amount']);
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title(__('Return recorded.'))->success()->send();
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('items.category'))
            ->columns([
                Tables\Columns\TextColumn::make('expense_number')
                    ->label(__('Number'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expense_date')
                    ->label(__('Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === Expense::TYPE_ADVANCE ? __('Advance') : __('Reimburse'))
                    ->color(fn (string $state): string => $state === Expense::TYPE_ADVANCE ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('recipient_name')
                    ->label(__('Requester'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('categories')
                    ->label(__('Category'))
                    ->state(fn (Expense $record): string => $record->items->map(fn ($item) => $item->category?->name)->filter()->unique()->join(', ')),
                Tables\Columns\TextColumn::make('advance_amount')
                    ->label(__('Advance'))
                    ->money('IDR', locale: 'id')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('receipt_amount')
                    ->label(__('Receipt'))
                    ->money('IDR', locale: 'id')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('still_with_requester')
                    ->label(__('With Requester'))
                    ->state(fn (Expense $record): ?float => $record->stillWithRequester() ?: null)
                    ->money('IDR', locale: 'id')
                    ->color('danger')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Expense::STATUS_OPEN => 'warning',
                        Expense::STATUS_SETTLED => 'success',
                        Expense::STATUS_CANCELLED => 'gray',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('excel')
                        ->label(__('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->with('items.category')->get();

                            return response()->streamDownload(function () use ($records) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    'Number', 'Date', 'Type', 'Category', 'Requester', 'Advance (Rp)', 'Receipt (Rp)', 'With Requester (Rp)', 'Status',
                                ]));

                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $record->expense_number,
                                        optional($record->expense_date)->format('Y-m-d') ?? '',
                                        $record->type,
                                        $record->items->map(fn ($item) => $item->category?->name)->filter()->unique()->join(', '),
                                        $record->recipient_name,
                                        $record->advance_amount !== null ? (float) $record->advance_amount : 0,
                                        $record->receipt_amount !== null ? (float) $record->receipt_amount : 0,
                                        $record->stillWithRequester(),
                                        $record->status,
                                    ]));
                                }

                                $writer->close();
                            }, 'expenses.xlsx');
                        }),

                    Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->with('items.category')->get();

                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.expenses-pdf', [
                                'records' => $records,
                                'title' => __('Expenses'),
                            ]);

                            return response()->streamDownload(fn () => print($pdf->output()), 'expenses.pdf');
                        }),

                    // Rekap sederhana Owner: total harga BARANG (nominal nota) per kategori, dari expense yang
                    // nilainya sudah nyata -- reimburse, dan kasbon yang notanya sudah dilaporkan (sisa uang yang
                    // belum kembali tidak mengubah biayanya). Kasbon yang belum dilaporkan masih estimasi.
                    Tables\Actions\Action::make('recap_by_category')
                        ->label(__('Recap by Category'))
                        ->icon('heroicon-o-chart-bar')
                        ->color('gray')
                        ->action(function ($livewire) {
                            $items = ExpenseItem::query()
                                ->with('category')
                                ->whereIn('expense_id', $livewire->getFilteredTableQuery()
                                    ->whereNotNull('receipt_amount')
                                    ->where('status', '!=', Expense::STATUS_CANCELLED)
                                    ->reorder()
                                    ->select('expenses.id'))
                                ->get()
                                ->groupBy(fn (ExpenseItem $item) => $item->category?->name ?? __('Uncategorized'));

                            return response()->streamDownload(function () use ($items) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    'Category', 'Count', 'Total Receipt (Rp)',
                                ]));

                                foreach ($items->sortKeys() as $categoryName => $rows) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        $categoryName,
                                        $rows->count(),
                                        (float) $rows->sum('amount'),
                                    ]));
                                }

                                $writer->close();
                            }, 'expenses-recap-by-category.xlsx');
                        }),
                ])
                    ->label(__('Export Data'))
                    ->icon('heroicon-m-arrow-down-tray')
                    ->button()
                    ->color('success'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()?->hasPermission('view_deleted_expenses') ?? false),

                // Pengganti tab "Open Advances": kasbon yang belum beres (belum lapor, atau sisa belum kembali).
                // Saat aktif, batas tanggal bulan berjalan tidak berlaku -- kasbon lama tetap harus terlihat.
                Tables\Filters\Filter::make('open')
                    ->label(__('Open Advances'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('status', Expense::STATUS_OPEN)),

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        Expense::STATUS_OPEN => __('Open'),
                        Expense::STATUS_SETTLED => __('Settled'),
                        Expense::STATUS_CANCELLED => __('Cancelled'),
                    ]),

                Tables\Filters\SelectFilter::make('expense_category_id')
                    ->label(__('Category'))
                    ->options(fn () => ExpenseCategory::orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('items', fn (Builder $items) => $items->where('expense_category_id', $data['value']))
                        : $query),

                // Silent date filter, standar modul transaksional (rujukan:
                // CashBookResource) -- default bulan berjalan ADA di form,
                // badge cuma tampil kalau user mengubahnya.
                Tables\Filters\Filter::make('expense_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('From'))
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('Until'))
                            ->default(now()),
                    ])
                    ->query(function (Builder $query, array $data, $livewire): Builder {
                        if (! empty($livewire->tableFilters['open']['isActive'])) {
                            return $query;
                        }

                        $from = $data['from'] ?? now()->startOfMonth()->format('Y-m-d');
                        $until = $data['until'] ?? now()->format('Y-m-d');

                        return $query
                            ->whereDate('expense_date', '>=', $from)
                            ->whereDate('expense_date', '<=', $until);
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        $defaultFrom = now()->startOfMonth()->format('Y-m-d');
                        $defaultUntil = now()->format('Y-m-d');

                        if (($data['from'] ?? null) && $data['from'] !== $defaultFrom) {
                            $indicators[] = Tables\Filters\Indicator::make(__('From').': '.Carbon::parse($data['from'])->format('d M Y'))
                                ->removeField('from');
                        }

                        if (($data['until'] ?? null) && $data['until'] !== $defaultUntil) {
                            $indicators[] = Tables\Filters\Indicator::make(__('Until').': '.Carbon::parse($data['until'])->format('d M Y'))
                                ->removeField('until');
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                static::settleAction(),
                static::returnAction(),

                Tables\Actions\Action::make('cancel_expense')
                    ->label(__('Cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Expense $record): bool => $record->awaitingReport() && static::canManage())
                    ->action(function (Expense $record): void {
                        if (! (auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('edit_expenses') ?? false))) {
                            Notification::make()->title(__('You do not have permission to do this.'))->danger()->send();

                            return;
                        }

                        try {
                            $record->cancel();
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title(__('Expense cancelled.'))->success()->send();
                    }),
            ])
            ->recordUrl(fn (Expense $record): string => $record->status === Expense::STATUS_OPEN && ! $record->trashed()
                ? Pages\EditExpense::getUrl([$record->id])
                : Pages\ViewExpense::getUrl([$record->id]))
            ->bulkActions([])
            ->defaultSort('id', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('Expense'))
                    ->schema([
                        Infolists\Components\TextEntry::make('expense_number')
                            ->label(__('Number'))
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge(),
                        Infolists\Components\TextEntry::make('expense_date')
                            ->label(__('Date'))
                            ->date('d M Y'),
                        Infolists\Components\TextEntry::make('recipient_name')
                            ->label(__('Requester')),
                        Infolists\Components\TextEntry::make('advance_amount')
                            ->label(__('Advance Amount'))
                            ->money('IDR', locale: 'id')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('receipt_amount')
                            ->label(__('Receipt Amount'))
                            ->money('IDR', locale: 'id')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('returned_amount')
                            ->label(__('Returned'))
                            ->money('IDR', locale: 'id')
                            ->visible(fn (Expense $record): bool => $record->type === Expense::TYPE_ADVANCE),
                        Infolists\Components\TextEntry::make('still_with_requester')
                            ->label(__('With Requester'))
                            ->state(fn (Expense $record): float => $record->stillWithRequester())
                            ->money('IDR', locale: 'id')
                            ->color('danger')
                            ->visible(fn (Expense $record): bool => $record->type === Expense::TYPE_ADVANCE),
                        Infolists\Components\TextEntry::make('description')
                            ->label(__('Description'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])->columns(2),

                Infolists\Components\Section::make(__('Items'))
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->schema([
                                Infolists\Components\TextEntry::make('category.name')
                                    ->label(__('Category')),
                                Infolists\Components\TextEntry::make('description')
                                    ->label(__('Description')),
                                Infolists\Components\TextEntry::make('amount')
                                    ->label(__('Total Price'))
                                    ->money('IDR', locale: 'id'),
                            ])->columns(3),
                    ]),

                Infolists\Components\Section::make(__('Bank Transactions'))
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('transactions')
                            ->hiddenLabel()
                            ->schema([
                                Infolists\Components\TextEntry::make('transaction_date')
                                    ->label(__('Date'))
                                    ->date('d M Y'),
                                Infolists\Components\TextEntry::make('type')
                                    ->label(__('Type'))
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => $state === 'in' ? __('Cash In') : __('Cash Out'))
                                    ->color(fn (string $state): string => $state === 'in' ? 'success' : 'danger'),
                                Infolists\Components\TextEntry::make('amount')
                                    ->label(__('Amount'))
                                    ->money('IDR', locale: 'id'),
                                Infolists\Components\TextEntry::make('description')
                                    ->label(__('Description')),
                            ])->columns(4),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
            'view' => Pages\ViewExpense::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return TrashedRecords::visibleTo(
            parent::getEloquentQuery(),
            'view_deleted_expenses',
        );
    }
}
