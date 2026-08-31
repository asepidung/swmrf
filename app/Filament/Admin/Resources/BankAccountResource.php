<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BankAccountResource\Pages;
use App\Filament\Admin\Resources\BankAccountResource\RelationManagers;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use Filament\Notifications\Notification;
use Filament\Support\RawJs;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';



    public static function getNavigationGroup(): ?string
    {
        return __('ACCOUNTING');
    }

    public static function getModelLabel(): string
    {
        return __('Bank Account');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Bank Accounts');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('initial')
                    ->label(__('Bank Initial'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->autofocus(),
                Forms\Components\TextInput::make('bank_name')
                    ->label(__('Bank Name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('account_number')
                    ->label(__('Account Number'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('account_holder')
                    ->label(__('Account Holder'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_active')
                    ->label(__('Is Active'))
                    ->default(true)
                    ->required()
                            ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('initial')
                    ->label(__('Bank Initial'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('bank_name')
                    ->label(__('Bank Name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('account_number')
                    ->label(__('Account Number'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('account_holder')
                    ->label(__('Account Holder'))
                    ->searchable(),
                /*
                 * Saldo DIHITUNG dari buku kas, tidak disimpan di master data.
                 *
                 * `withSum` dipakai supaya satu halaman tabel tidak memicu satu
                 * query per baris: angkanya dijumlahkan di database, bukan
                 * dengan memuat seluruh mutasi ke memori.
                 */
                Tables\Columns\TextColumn::make('balance')
                    ->label(__('Balance'))
                    ->state(fn (BankAccount $record): float => (float) ($record->transactions_in_sum ?? 0) - (float) ($record->transactions_out_sum ?? 0))
                    ->money('IDR', locale: 'id')
                    ->weight('bold')
                    ->color(fn ($state): string => $state < 0 ? 'danger' : 'success')
                    ->description(fn (BankAccount $record): ?string => $record->openingBalanceEntry()
                        ? null
                        : __('Opening balance not set')),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Is Active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(
                fn (BankAccount $record): string => Pages\EditBankAccount::getUrl([$record->getKey()]),
            )
            ->filters([
                //
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withSum(['transactions as transactions_in_sum' => fn (Builder $q) => $q->where('type', 'in')], 'amount')
                ->withSum(['transactions as transactions_out_sum' => fn (Builder $q) => $q->where('type', 'out')], 'amount'))
            ->actions([
                Tables\Actions\Action::make('setOpeningBalance')
                    ->label(__('Opening Balance'))
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    // Menyetel saldo awal berarti menciptakan uang di buku kas.
                    // Boleh MELIHAT rekening tidak otomatis berarti boleh
                    // melakukannya -- lihat MoneyActionPermissionTest.
                    ->visible(fn (BankAccount $record): bool => auth()->user()->hasPermission('set_opening_balance')
                        && $record->canSetOpeningBalance())
                    ->form([
                        Forms\Components\DatePicker::make('transaction_date')
                            ->label(__('Date'))
                            ->default(now())
                            ->required(),
                        Forms\Components\TextInput::make('amount_input')
                            ->label(__('Opening Balance'))
                            ->prefix('Rp')
                            ->required()
                            ->extraInputAttributes(['inputmode' => 'numeric'])
                            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                            ->helperText(__('Recorded as an entry in the Cash Book, not as a number on this account.')),
                    ])
                    ->fillForm(function (BankAccount $record): array {
                        $entry = $record->openingBalanceEntry();

                        return [
                            'amount_input' => $entry ? number_format((float) $entry->amount, 0, ',', '.') : null,
                            'transaction_date' => $entry?->transaction_date ?? now(),
                        ];
                    })
                    ->action(function (BankAccount $record, array $data): void {
                        // Formatnya gaya Indonesia; titik adalah pemisah ribuan,
                        // bukan desimal. Tanpa dibuang, "2.000.000" terbaca PHP
                        // sebagai 2.0 dan saldo awal menyusut tanpa error.
                        $amount = (float) str_replace('.', '', $data['amount_input']);

                        if ($amount <= 0) {
                            Notification::make()
                                ->danger()
                                ->title(__('Opening balance must be greater than zero'))
                                ->send();

                            return;
                        }

                        // Menimpa baris yang sudah ada, bukan menambah baris
                        // kedua: sebuah rekening hanya punya satu titik awal.
                        $entry = $record->openingBalanceEntry() ?? new BankTransaction();

                        $entry->fill([
                            'bank_account_id' => $record->id,
                            'type' => 'in',
                            'amount' => $amount,
                            'reference_type' => BankAccount::OPENING_BALANCE_REFERENCE,
                            'description' => __('Opening balance for :account', ['account' => $record->initial]),
                            'transaction_date' => $data['transaction_date'],
                        ])->save();

                        Notification::make()
                            ->success()
                            ->title(__('Opening balance saved'))
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListBankAccounts::route('/'),
            'create' => Pages\CreateBankAccount::route('/create'),
            'edit' => Pages\EditBankAccount::route('/{record}/edit'),
        ];
    }
}
