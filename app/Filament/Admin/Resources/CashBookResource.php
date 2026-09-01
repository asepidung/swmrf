<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CashBookResource\Pages;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Buku Kas: seluruh uang yang benar-benar keluar dan masuk.
 *
 * Tabelnya (`bank_transactions`) sudah terisi sejak 26 Agustus 2026 -- DP ke
 * supplier menulis 'out', penerimaan piutang menulis 'in' -- tetapi tidak ada
 * satu pun halaman yang menampilkannya. Uang bergerak dan tercatat rapi di
 * tempat yang tidak bisa dilihat siapa pun.
 *
 * Read-only dengan sengaja: setiap baris di sini adalah JEJAK dari dokumen
 * lain. Membolehkan orang mengetik baris kas secara langsung akan memutus
 * hubungan itu dan membuat buku kas berbeda dari dokumen yang melahirkannya,
 * tanpa ada yang bisa menunjukkan mana yang benar.
 *
 * Namanya "Buku Kas", bukan "Bank Transactions", karena tabel ini juga
 * menampung akun KAS tunai -- bukan cuma rekening bank.
 */
class CashBookResource extends Resource
{
    protected static ?string $model = BankTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return __('ACCOUNTING');
    }

    public static function getModelLabel(): string
    {
        return __('Cash Book');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Cash Book');
    }

    /**
     * Resource ini memakai model milik modul lain (BankTransaction), jadi
     * Policy otomatis tidak akan pernah tepat sasaran. Gerbangnya wajib
     * dideklarasikan di sini -- lihat ResourceAccessGateTest.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('view_cash_book') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label(__('Date'))
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('bankAccount.initial')
                    ->label(__('Account'))
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'in' ? __('Cash In') : __('Cash Out'))
                    ->color(fn (string $state): string => $state === 'in' ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->money('IDR', locale: 'id')
                    ->weight('bold')
                    ->color(fn ($record): string => $record->type === 'in' ? 'success' : 'danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label(__('Description'))
                    ->wrap()
                    ->searchable(),
            ])
            ->headerActions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('excel')
                        ->label(__('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->with('bankAccount')->get();

                            return response()->streamDownload(function () use ($records) {
                                $writer = new \OpenSpout\Writer\XLSX\Writer();
                                $writer->openToFile('php://output');
                                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                    'Date', 'Account', 'Type', 'Cash In (Rp)', 'Cash Out (Rp)', 'Description',
                                ]));

                                foreach ($records as $record) {
                                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                        optional($record->transaction_date)->format('Y-m-d') ?? '',
                                        $record->bankAccount?->initial ?? '',
                                        $record->type === 'in' ? 'Cash In' : 'Cash Out',
                                        $record->type === 'in' ? (float) $record->amount : 0,
                                        $record->type === 'out' ? (float) $record->amount : 0,
                                        $record->description ?? '',
                                    ]));
                                }

                                $writer->close();
                            }, 'cash-book.xlsx');
                        }),

                    Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->with('bankAccount')->get();

                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.cash-book-pdf', [
                                'records' => $records,
                                'title' => __('Cash Book'),
                            ]);

                            return response()->streamDownload(fn () => print($pdf->output()), 'cash-book.pdf');
                        }),
                ])
                    ->label(__('Export Data'))
                    ->icon('heroicon-m-arrow-down-tray')
                    ->button()
                    ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bank_account_id')
                    ->label(__('Account'))
                    ->options(fn () => BankAccount::orderBy('initial')->pluck('initial', 'id')),

                Tables\Filters\SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options([
                        'in' => __('Cash In'),
                        'out' => __('Cash Out'),
                    ]),

                // Silent date filter: tanggal 1 bulan berjalan sampai hari ini,
                // tanpa badge indikator, mengikuti standar modul transaksional.
                Tables\Filters\Filter::make('transaction_date')
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
                            ->whereDate('transaction_date', '>=', $from)
                            ->whereDate('transaction_date', '<=', $until);
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
            ->actions([])
            ->bulkActions([])
            // Tanggal saja tidak cukup: beberapa catatan pada HARI YANG
            // SAMA tidak punya urutan yang pasti, sehingga yang barusan
            // dibuat bisa muncul di bawah yang lebih dulu. Id dipakai sebagai
            // pemecah seri karena ia selalu menaik.
            ->defaultSort(fn ($query) => $query
                ->orderBy('transaction_date', 'desc')
                ->orderBy('id', 'desc'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashBook::route('/'),
        ];
    }
}
