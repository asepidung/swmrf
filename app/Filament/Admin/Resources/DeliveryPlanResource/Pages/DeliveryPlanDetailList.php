<?php

namespace App\Filament\Admin\Resources\DeliveryPlanResource\Pages;

use App\Filament\Admin\Resources\DeliveryPlanResource;
use App\Models\SalesOrder;
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class DeliveryPlanDetailList extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = DeliveryPlanResource::class;

    protected static string $view = 'filament.admin.resources.delivery-plan.pages.detail-list';

    protected static ?string $title = 'Plan Delivery Items Detail';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SalesOrder::query()
                    ->whereNotNull('delivery_plan_id')
                    ->with(['customer', 'deliveryPlan'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('deliveryPlan.delivery_date')
                    ->label(__('Delivery Date'))
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('so_number')
                    ->label(__('SO Number'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('total_weight')
                    ->label(__('Qty (Kg)'))
                    ->state(fn (SalesOrder $record) => $record->items()->sum('weight'))
                    ->numeric()
                    ->alignRight(),
                Tables\Columns\TextColumn::make('deliveryPlan.driver')
                    ->label(__('Driver'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deliveryPlan.armada')
                    ->label(__('Armada'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deliveryPlan.load_time')
                    ->label(__('Jam Loading'))
                    ->time('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('delivery_note')
                    ->label(__('Note'))
                    ->limit(30),
            ])
            ->filters([
                Tables\Filters\Filter::make('delivery_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('delivery_from')
                            ->label(__('From Date')),
                        \Filament\Forms\Components\DatePicker::make('delivery_until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['delivery_from'] ?? now()->startOfMonth()->toDateString();
                        $until = $data['delivery_until'] ?? null;

                        return $query->whereHas('deliveryPlan', function ($q) use ($from, $until) {
                            $q->when(
                                $from,
                                fn ($q, $date) => $q->whereDate('delivery_date', '>=', $date)
                            )->when(
                                $until,
                                fn ($q, $date) => $q->whereDate('delivery_date', '<=', $date)
                            );
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['delivery_from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['delivery_from'])->format('d M Y');
                        }
                        if ($data['delivery_until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['delivery_until'])->format('d M Y');
                        }
                        return $indicators;
                    }),
            ])
            ->headerActions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('excel')
                        ->label(__('Excel'))
                        ->icon('heroicon-o-document-text')
                        ->action(function ($livewire) {
                            // Using standard csv/xlsx export or custom action to print
                            // We can use openspout to build it or custom callback.
                            // Since we have a flat list of Sales Orders, we can construct the CSV download directly
                            $records = $livewire->getFilteredTableQuery()->get();
                            
                            $headers = [
                                "Content-type" => "text/csv",
                                "Content-Disposition" => "attachment; filename=plan-delivery-details.csv",
                                "Pragma" => "no-cache",
                                "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
                                "Expires" => "0"
                            ];

                            $callback = function() use ($records) {
                                $file = fopen('php://output', 'w');
                                fputcsv($file, ['No', 'Delivery Date', 'Customer', 'SO Number', 'Qty (Kg)', 'Driver', 'Armada', 'Jam Loading', 'Note']);

                                foreach ($records as $index => $row) {
                                    fputcsv($file, [
                                        $index + 1,
                                        $row->deliveryPlan->delivery_date,
                                        $row->customer->name,
                                        $row->so_number,
                                        $row->items()->sum('weight'),
                                        $row->deliveryPlan->driver,
                                        $row->deliveryPlan->armada,
                                        $row->deliveryPlan->load_time,
                                        $row->delivery_note
                                    ]);
                                }
                                fclose($file);
                            };

                            return response()->stream($callback, 200, $headers);
                        }),
                    Tables\Actions\Action::make('pdf')
                        ->label(__('PDF'))
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(function ($livewire) {
                            $records = $livewire->getFilteredTableQuery()->get();
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.delivery-plan-details-pdf', [
                                'records' => $records
                            ]);
                            return response()->streamDownload(fn () => print($pdf->output()), 'plan-delivery-details.pdf');
                        }),
                ])
                ->label(__('Export Data'))
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('success'),
            ])
            ->defaultSort('id', 'desc');
    }
}
