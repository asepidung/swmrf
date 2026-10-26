<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\ExpenseResource;
use App\Models\Expense;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Tanggungan pemohon di Dashboard (keputusan Owner 27 Oktober 2026): uang kasbon yang masih di tangan
 * pemohon -- belum lapor nota, atau sudah lapor tapi sisanya belum dikembalikan. Terus tampil sampai
 * beres. Hanya untuk pemegang `create_expenses`, dan hanya kalau memang ada yang belum beres.
 */
class ExpenseLiabilityWidget extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -1;

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user || ! ($user->isProgrammer() || $user->hasPermission('create_expenses'))) {
            return false;
        }

        return Expense::where('status', Expense::STATUS_OPEN)->where('type', Expense::TYPE_ADVANCE)->exists();
    }

    /** Uang di tangan pemohon per dokumen: seluruh uang muka kalau belum lapor, selain itu sisa yang belum kembali. */
    public static function stillWithRequesterSql(): string
    {
        return 'CASE WHEN receipt_amount IS NULL THEN advance_amount ELSE advance_amount - receipt_amount - returned_amount END';
    }

    public function table(Table $table): Table
    {
        $owed = static::stillWithRequesterSql();

        return $table
            ->heading(__('Advances still with the requester'))
            ->query(
                Expense::query()
                    ->where('status', Expense::STATUS_OPEN)
                    ->where('type', Expense::TYPE_ADVANCE)
                    ->select([
                        DB::raw('MIN(id) as id'),
                        'recipient_name',
                        DB::raw('COUNT(*) as documents'),
                        DB::raw('MIN(expense_date) as oldest_date'),
                        DB::raw("SUM($owed) as owed"),
                    ])
                    ->groupBy('recipient_name')
            )
            ->columns([
                Tables\Columns\TextColumn::make('recipient_name')
                    ->label(__('Requester'))
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('documents')
                    ->label(__('Open advances')),
                Tables\Columns\TextColumn::make('oldest_date')
                    ->label(__('Oldest'))
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('owed')
                    ->label(__('With Requester'))
                    ->money('IDR', locale: 'id')
                    ->color('danger')
                    ->weight('bold'),
            ])
            ->defaultSort('owed', 'desc')
            ->paginated(false)
            ->recordUrl(fn (): string => ExpenseResource::getUrl('index', ['activeTab' => 'open']));
    }
}
