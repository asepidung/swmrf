<?php

namespace App\Filament\Admin\Resources\BoningResource\Pages;

use App\Filament\Admin\Resources\BoningResource;
use App\Models\Boning;
use App\Models\Carcass;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Draft Boning: karkas yang menunggu boning, dipilih beberapa sekaligus lalu satu aksi "Buat Boning" (pola yang sama dengan Draft Tally).
 *
 * Yang tampil hanya karkas yang belum dipakai boning mana pun dan bukan riwayat impor legacy (`Carcass::waitingForBoning()`;
 * keputusan Owner, 22 Oktober 2026). Saat boning dibuat, karkas dikunci dan diperiksa ulang di dalam transaksi supaya dua orang
 * yang memilih karkas yang sama tidak sama-sama berhasil.
 */
class DraftBoning extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = BoningResource::class;

    protected static string $view = 'filament.admin.resources.boning-resource.pages.draft-boning';

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->hasPermission('create_bonings') ?? false;
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Draft Boning');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Carcass::query()->waitingForBoning()->with(['weighing.receiving.supplier'])->withCount('items')->orderByDesc('kill_date')->orderByDesc('id'))
            ->heading(__('Carcasses waiting for boning'))
            ->columns([
                Tables\Columns\TextColumn::make('carcass_number')
                    ->label(__('Carcass Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('kill_date')
                    ->label(__('Kill date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('weighing.receiving.supplier.name')
                    ->label(__('Supplier'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label(__('Heads'))
                    ->alignRight(),
                Tables\Columns\TextColumn::make('carcass_weight')
                    ->label(__('Carcass weight'))
                    ->state(fn (Carcass $record): string => number_format($record->carcassWeight(), 2, ',', '.').' kg')
                    ->alignRight(),
            ])
            ->defaultSort('kill_date', 'desc')
            ->bulkActions([
                Tables\Actions\BulkAction::make('create_boning')
                    ->label(__('Create Boning'))
                    ->icon('heroicon-m-arrow-right-circle')
                    ->color('primary')
                    ->deselectRecordsAfterCompletion()
                    ->authorize(fn (): bool => auth()->user()?->hasPermission('create_bonings') ?? false)
                    ->form([
                        Forms\Components\DatePicker::make('boning_date')
                            ->label(__('Boning Date'))
                            ->required()
                            ->default(now()),
                        Forms\Components\Textarea::make('note')
                            ->label(__('Note')),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $ids = $records->pluck('id')->all();

                        $boning = DB::transaction(function () use ($ids, $data): ?Boning {
                            // Karkas dikunci lalu dihitung ulang: yang sudah diambil boning lain membatalkan seluruh pembuatan.
                            $free = Carcass::query()->whereKey($ids)->lockForUpdate()->waitingForBoning()->pluck('id');

                            if ($free->count() !== count($ids)) {
                                return null;
                            }

                            $boning = Boning::create([
                                'boning_date' => $data['boning_date'],
                                'note' => $data['note'] ?? null,
                                'status' => 'OPEN',
                                'created_by' => auth()->id(),
                            ]);

                            foreach ($ids as $id) {
                                $boning->carcasses()->create(['carcass_id' => $id]);
                            }

                            return $boning;
                        });

                        if (! $boning) {
                            Notification::make()
                                ->title(__('Some selected carcasses were already taken by another boning. Nothing was created; refresh and select again.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()->title(__('Boning created').': '.$boning->doc_no)->success()->send();

                        return redirect()->to(BoningResource::getUrl('labeling', ['record' => $boning->id]));
                    }),
            ]);
    }
}
