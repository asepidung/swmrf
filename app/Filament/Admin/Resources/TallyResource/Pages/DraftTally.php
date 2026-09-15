<?php

namespace App\Filament\Admin\Resources\TallyResource\Pages;

use App\Filament\Admin\Resources\TallyResource;
use App\Models\SalesOrder;
use App\Models\Tally;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Filament\Forms;

class DraftTally extends Page implements HasTable
{
    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return __('Draft Tally');
    }

    use InteractsWithTable;

    protected static string $resource = TallyResource::class;

    protected static string $view = 'filament.admin.resources.tally-resource.pages.draft-tally';


    public function table(Table $table): Table
    {
        return $table
            ->query(
                SalesOrder::query()
                    ->where('status', SalesOrder::STATUS_WAITING)
                    ->orderBy('delivery_date', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('so_number')
                    ->label(__('SO Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_date')
                    ->label(__('Delivery Date'))
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('po_number')
                    ->label(__('PO Number'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('note')
                    ->label(__('Note'))
                    ->limit(50),
            ])
            ->actions([
                Tables\Actions\Action::make('process')
                    ->label(__('Create Tally'))
                    ->icon('heroicon-m-arrow-right-circle')
                    ->color('primary')
                    ->hidden(fn () => ! auth()->user()?->hasPermission('create_tallies'))
                    ->authorize(fn (): bool => auth()->user()?->hasPermission('create_tallies') ?? false)
                    ->form([
                        Forms\Components\TextInput::make('pod_limit')
                            ->label(__('Max POD Age (Days)'))
                            ->numeric()
                            ->required()
                            ->default(fn () => session('tally_pod_limit', 30)),
                    ])
                    ->action(function (SalesOrder $record, array $data) {
                        $ditolak = false;

                        $tally = DB::transaction(function () use ($record, $data, &$ditolak) {
                            // Baris SO dikunci dan statusnya dibaca ULANG
                            // sebelum Tally dibuat. Tanpa ini, dua klik
                            // "Create Tally" yang bersamaan (atau klik ganda)
                            // untuk SO yang sama bisa sama-sama lolos dan
                            // sama-sama membuat Tally -- yang kedua menabrak
                            // unique constraint sales_order_id mentah.
                            $locked = SalesOrder::whereKey($record->id)->lockForUpdate()->first();

                            if (! $locked || $locked->status !== SalesOrder::STATUS_WAITING) {
                                $ditolak = true;

                                return null;
                            }

                            session(['tally_pod_limit' => (int) $data['pod_limit']]);

                            $tally = Tally::create([
                                'sales_order_id' => $record->id,
                                'status' => Tally::STATUS_PROCESSING,
                            ]);

                            $locked->update(['status' => SalesOrder::STATUS_PROCESSING]);

                            activity('tally')
                                ->performedOn($tally)
                                ->log('Buat Data Tally: ' . $tally->tally_number);

                            return $tally;
                        });

                        if ($ditolak) {
                            Notification::make()
                                ->title(__('This Sales Order is no longer waiting for a tally'))
                                ->body(__('It may have just been tallied or cancelled from another session.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('Tally Created Successfully'))
                            ->success()
                            ->send();

                        return redirect()->to(TallyResource::getUrl('scan', ['record' => $tally->id]));
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label(__('Cancel'))
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (SalesOrder $record) {
                        $ditolak = false;

                        DB::transaction(function () use ($record, &$ditolak) {
                            // Dikunci dan dibaca ulang dengan alasan yang
                            // sama dengan process(): mencegah race di mana
                            // SO ini baru saja mendapat Tally (proses()
                            // menang lebih dulu) tetapi cancel() yang
                            // membaca state lama tetap menimpanya menjadi
                            // dibatalkan.
                            $locked = SalesOrder::whereKey($record->id)->lockForUpdate()->first();

                            if (! $locked || $locked->status !== SalesOrder::STATUS_WAITING) {
                                $ditolak = true;

                                return;
                            }

                            $locked->update(['status' => SalesOrder::STATUS_CANCELLED]);

                            activity('sales_order')
                                ->performedOn($locked)
                                ->log('Cancel Sales Order: ' . $locked->so_number);
                        });

                        if ($ditolak) {
                            Notification::make()
                                ->title(__('This Sales Order is no longer waiting'))
                                ->body(__('It may have just been tallied from another session.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('Sales Order Cancelled'))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
