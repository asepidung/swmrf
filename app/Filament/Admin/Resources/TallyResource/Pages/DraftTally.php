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
    use InteractsWithTable;

    protected static string $resource = TallyResource::class;

    protected static string $view = 'filament.admin.resources.tally-resource.pages.draft-tally';

    protected static ?string $title = 'Draft Tally';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SalesOrder::query()
                    ->where('status', 'waiting')
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
                    ->form([
                        Forms\Components\TextInput::make('pod_limit')
                            ->label(__('Max POD Age (Days)'))
                            ->numeric()
                            ->required()
                            ->default(fn () => session('tally_pod_limit', 30)),
                    ])
                    ->action(function (SalesOrder $record, array $data) {
                        return DB::transaction(function () use ($record, $data) {
                            session(['tally_pod_limit' => (int) $data['pod_limit']]);

                            $tally = Tally::create([
                                'sales_order_id' => $record->id,
                                'status' => 'processing',
                            ]);

                            $record->update(['status' => 'processing']);

                            activity('tally')
                                ->performedOn($tally)
                                ->log('Buat Data Tally: ' . $tally->tally_number);

                            Notification::make()
                                ->title(__('Tally Created Successfully'))
                                ->success()
                                ->send();

                            return redirect()->to(TallyResource::getUrl('scan', ['record' => $tally->id]));
                        });
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label(__('Cancel'))
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (SalesOrder $record) {
                        DB::transaction(function () use ($record) {
                            $record->update(['status' => 'cancelled']);

                            activity('sales_order')
                                ->performedOn($record)
                                ->log('Cancel Sales Order: ' . $record->so_number);
                        });

                        Notification::make()
                            ->title(__('Sales Order Cancelled'))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
