<?php

namespace App\Filament\Resources\AccountPayableResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Auth;
use Filament\Support\RawJs;
use Closure;

class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';
    protected static ?string $title = 'Payment History';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('created_by')
                    ->default(fn() => Auth::id()),

                Forms\Components\Section::make('Transaction Details')
                    ->schema([
                        Forms\Components\Placeholder::make('balance_due')
                            ->label('Remaining Balance')
                            ->content(function ($livewire) {
                                $balance = $livewire->getOwnerRecord()->balance_due ?? 0;
                                return 'Rp ' . number_format($balance, 0, ',', '.');
                            }),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Payment Date')
                            ->default(now())
                            ->required(),

                        // UPDATE: Pake ID Bank buat Ledger, tapi tetep simpan teks ke payment_method
                        Forms\Components\Select::make('company_bank_id')
                            ->label('Source Account (Bank)')
                            ->options(\App\Models\CompanyBank::where('is_active', true)->pluck('initial', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                $bank = \App\Models\CompanyBank::find($state);
                                // Simpan string lengkap ke kolom payment_method buat kebutuhan Voucher/Print
                                $set('payment_method', $bank?->full_account);
                            }),

                        Forms\Components\Hidden::make('payment_method'),

                        Forms\Components\TextInput::make('amount_paid')
                            ->label('Payment Amount')
                            ->prefix('Rp')
                            ->required()
                            ->mask(RawJs::make(<<<'JS'
                                $input.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')
                            JS))
                            ->stripCharacters('.')
                            ->live(onBlur: true)
                            ->rules([
                                fn(Get $get, $livewire) => function (string $attribute, $value, Closure $fail) use ($get, $livewire) {
                                    $balance = $livewire->getOwnerRecord()->balance_due ?? 0;
                                    $paid = (float) preg_replace('/[^0-9]/', '', $get('amount_paid') ?? 0);
                                    $discount = (float) preg_replace('/[^0-9]/', '', $get('discount_amount') ?? 0);

                                    if (($paid + $discount) > $balance) {
                                        $fail('Overpayment! Amount + Discount exceeds Remaining Balance.');
                                    }
                                },
                            ])
                            ->hintAction(
                                Forms\Components\Actions\Action::make('pay_full')
                                    ->label('Pay Full Balance')
                                    ->icon('heroicon-m-banknotes')
                                    ->color('success')
                                    ->action(function (Set $set, $livewire) {
                                        $balance = $livewire->getOwnerRecord()->balance_due ?? 0;
                                        $set('amount_paid', number_format($balance, 0, '', '.'));
                                        $set('discount_amount', '0');
                                    })
                            ),

                        Forms\Components\TextInput::make('proof_of_payment')
                            ->label('Ref. Number (Optional)')
                            ->placeholder('e.g., TRF-BCA-123')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Adjustments (Discount)')
                    ->schema([
                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Discount / Adjustment')
                            ->prefix('Rp')
                            ->default(0)
                            ->mask(RawJs::make(<<<'JS'
                                $input.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')
                            JS))
                            ->stripCharacters('.')
                            ->live(onBlur: true)
                            ->rules([
                                fn(Get $get, $livewire) => function (string $attribute, $value, Closure $fail) use ($get, $livewire) {
                                    $balance = $livewire->getOwnerRecord()->balance_due ?? 0;
                                    $paid = (float) preg_replace('/[^0-9]/', '', $get('amount_paid') ?? 0);
                                    $discount = (float) preg_replace('/[^0-9]/', '', $get('discount_amount') ?? 0);

                                    if (($paid + $discount) > $balance) {
                                        $fail('Invalid Adjustment! Amount + Discount exceeds Remaining Balance.');
                                    }
                                },
                            ]),

                        Forms\Components\Placeholder::make('total_debt_reduction_display')
                            ->label('Total Debt Reduction')
                            ->content(function (Get $get) {
                                $paid = (float) preg_replace('/[^0-9]/', '', $get('amount_paid') ?? 0);
                                $discount = (float) preg_replace('/[^0-9]/', '', $get('discount_amount') ?? 0);
                                $total = $paid + $discount;

                                return 'Rp ' . number_format($total, 0, ',', '.');
                            }),
                    ])->columns(2),

                Forms\Components\Textarea::make('note')
                    ->label('Note / Description')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('payment_date')->label('Date')->date('d-M-Y')->sortable(),
                Tables\Columns\TextColumn::make('amount_paid')->label('Amount Paid')->money('IDR'),
                Tables\Columns\TextColumn::make('discount_amount')->label('Discount')->money('IDR')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total_debt_reduction')->label('Total Reduction')->money('IDR')->weight('bold'),
                Tables\Columns\TextColumn::make('payment_method')->label('Method / Bank'),
                Tables\Columns\TextColumn::make('proof_of_payment')->label('Ref Number')->default('-'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('back_to_list')
                    ->label('Back to List')
                    ->icon('heroicon-m-arrow-left')
                    ->color('gray')
                    ->url(fn() => \App\Filament\Resources\AccountPayableResource::getUrl('index')),

                Tables\Actions\CreateAction::make()
                    ->label('New Payment')
                    ->icon('heroicon-o-currency-dollar')
                    ->modalHeading('Record Payment')
                    ->createAnother(false)
                    ->mutateFormDataUsing(function (array $data) {
                        $data['created_by'] = Auth::id();
                        $data['total_debt_reduction'] = (float)($data['amount_paid'] ?? 0) + (float)($data['discount_amount'] ?? 0);
                        $data['tax_deduction_amount'] = 0;
                        return $data;
                    })
                    ->successRedirectUrl(fn() => \App\Filament\Resources\AccountPayableResource::getUrl('index')),
            ])
            ->actions([
                Tables\Actions\Action::make('print')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn($record) => route('vouchers.bank-out.print', $record->id))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        $data['total_debt_reduction'] = (float)($data['amount_paid'] ?? 0) + (float)($data['discount_amount'] ?? 0);
                        $data['tax_deduction_amount'] = 0;
                        return $data;
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('payment_date', 'desc');
    }
}
