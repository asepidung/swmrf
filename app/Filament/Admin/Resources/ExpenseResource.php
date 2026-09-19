<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ExpenseResource\Pages;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
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

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('FINANCE');
    }

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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Expense'))
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label(__('Type'))
                            ->options([
                                Expense::TYPE_ADVANCE => __('Advance (Kasbon)'),
                                Expense::TYPE_REIMBURSE => __('Reimburse'),
                            ])
                            ->required()
                            ->live()
                            // Jenis dokumen menentukan BankTransaction yang
                            // lahir saat dibuat -- tidak boleh diganti
                            // sesudahnya tanpa menulis ulang jejak kasnya.
                            ->disabled(fn (string $context): bool => $context === 'edit')
                            ->dehydrated(),

                        Forms\Components\DatePicker::make('expense_date')
                            ->label(__('Date'))
                            ->required()
                            ->default(now()),

                        Forms\Components\Select::make('bank_account_id')
                            ->label(__('Cash/Bank Account'))
                            ->options(fn () => BankAccount::where('is_active', true)->pluck('initial', 'id'))
                            ->default(fn () => BankAccount::cashAccount()->id)
                            ->searchable()
                            ->required()
                            ->disabled(fn (string $context): bool => $context === 'edit')
                            ->dehydrated(),

                        Forms\Components\Select::make('expense_category_id')
                            ->label(__('Category'))
                            ->options(fn () => ExpenseCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('recipient_name')
                            ->label(__('Recipient'))
                            ->required()
                            ->maxLength(255)
                            ->extraInputAttributes(['style' => 'text-transform:uppercase']),

                        Forms\Components\Select::make('user_id')
                            ->label(__('Recipient Account (optional)'))
                            ->options(fn () => User::where('is_active', true)->pluck('name', 'id'))
                            ->searchable(),

                        static::money('advance_amount')
                            ->label(__('Advance Amount'))
                            ->visible(fn (Forms\Get $get): bool => $get('type') === Expense::TYPE_ADVANCE)
                            ->required(fn (Forms\Get $get): bool => $get('type') === Expense::TYPE_ADVANCE)
                            ->disabled(fn (string $context): bool => $context === 'edit')
                            ->dehydrated(),

                        static::money('receipt_amount')
                            ->label(__('Receipt Amount'))
                            ->visible(fn (Forms\Get $get): bool => $get('type') === Expense::TYPE_REIMBURSE)
                            ->required(fn (Forms\Get $get): bool => $get('type') === Expense::TYPE_REIMBURSE)
                            ->disabled(fn (string $context): bool => $context === 'edit')
                            ->dehydrated(),

                        Forms\Components\Textarea::make('description')
                            ->label(__('Description'))
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('recipient_name')
                    ->label(__('Recipient'))
                    ->searchable(),
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
                Tables\Columns\TextColumn::make('balance_due')
                    ->label(__('Balance Due'))
                    ->money('IDR', locale: 'id')
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
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()?->hasPermission('view_deleted_expenses') ?? false),

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        Expense::STATUS_OPEN => __('Open'),
                        Expense::STATUS_SETTLED => __('Settled'),
                        Expense::STATUS_CANCELLED => __('Cancelled'),
                    ]),

                Tables\Filters\SelectFilter::make('expense_category_id')
                    ->label(__('Category'))
                    ->relationship('category', 'name'),

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
                    ->query(function (Builder $query, array $data): Builder {
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
                Tables\Actions\Action::make('settle')
                    ->label(__('Settle'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Expense $record): bool => $record->status === Expense::STATUS_OPEN
                        && (auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('edit_expenses') ?? false)))
                    ->form([
                        static::money('receipt_amount')
                            ->label(__('Receipt Amount'))
                            ->required(),
                        Forms\Components\FileUpload::make('receipt_photo')
                            ->label(__('Receipt Photo'))
                            ->disk('local')
                            ->directory('expense-receipts')
                            ->visibility('private')
                            ->image()
                            ->maxSize(5120),
                    ])
                    ->action(function (Expense $record, array $data): void {
                        if (! (auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('edit_expenses') ?? false))) {
                            Notification::make()->title(__('You do not have permission to do this.'))->danger()->send();

                            return;
                        }

                        try {
                            // Foto disimpan SELAGI masih Open -- settle()
                            // sendiri langsung mengunci baris ke Settled,
                            // dan update() sesudahnya akan ditolak guard
                            // `updating()` di model.
                            if (! empty($data['receipt_photo'])) {
                                $record->update(['receipt_photo' => $data['receipt_photo']]);
                            }

                            $record->settle((float) $data['receipt_amount']);
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()->title(__('Failed'))->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title(__('Expense settled.'))->success()->send();
                    }),

                Tables\Actions\Action::make('cancel_expense')
                    ->label(__('Cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Expense $record): bool => $record->status === Expense::STATUS_OPEN
                        && (auth()->user()?->isProgrammer() || (auth()->user()?->hasPermission('edit_expenses') ?? false)))
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
                        Infolists\Components\TextEntry::make('category.name')
                            ->label(__('Category')),
                        Infolists\Components\TextEntry::make('recipient_name')
                            ->label(__('Recipient')),
                        Infolists\Components\TextEntry::make('advance_amount')
                            ->label(__('Advance Amount'))
                            ->money('IDR', locale: 'id')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('receipt_amount')
                            ->label(__('Receipt Amount'))
                            ->money('IDR', locale: 'id')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('balance_due')
                            ->label(__('Balance Due'))
                            ->money('IDR', locale: 'id')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('description')
                            ->label(__('Description'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])->columns(2),

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
