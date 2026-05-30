<?php

namespace App\Filament\Resources\TallySheetResource\Pages;

use App\Filament\Resources\TallySheetResource;
use App\Models\SalesOrder;
use App\Models\TallySheet;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class ListTallySheets extends ListRecords
{
    protected static string $resource = TallySheetResource::class;

    protected function getHeaderActions(): array
    {
        $getWaitingSOs = function () {
            return SalesOrder::where('status', 'waiting')
                ->with('customer')
                ->orderBy('delivery_date', 'asc')
                ->get()
                ->mapWithKeys(function ($so) {
                    $customerName = $so->customer->name ?? 'No Customer';
                    $deliveryDate = Carbon::parse($so->delivery_date)->format('d M Y');
                    $poNumber = $so->po_number ?? '-';
                    return [$so->id => "{$customerName} | Kirim: {$deliveryDate} | PO: {$poNumber} | SO: {$so->so_number}"];
                })
                ->toArray();
        };

        return [
            Actions\Action::make('draft_tally')
                ->label('Draft Tally')
                ->color('warning')
                ->icon('heroicon-o-document-text')
                ->modalHeading('Proses Sales Order (Waiting)')
                ->modalSubmitActionLabel('Eksekusi')
                ->modalCancelAction(false)
                ->form([
                    Forms\Components\Select::make('sales_order_id')
                        ->label('Pilih Sales Order')
                        ->options($getWaitingSOs)
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Radio::make('tindakan')
                        ->label('Pilih Tindakan')
                        ->options([
                            'tally' => 'Mulai Tally Baru (On Process)',
                            'cancel' => 'Batalkan SO (Cancel Data)',
                        ])
                        ->default('tally')
                        ->inline()
                        ->required()
                        ->live(),

                    Forms\Components\DatePicker::make('tally_date')
                        ->label('Tanggal Tally')
                        ->default(now())
                        ->visible(fn(Forms\Get $get) => $get('tindakan') === 'tally')
                        ->required(fn(Forms\Get $get) => $get('tindakan') === 'tally'),
                ])
                ->action(function (array $data) {
                    $so = SalesOrder::find($data['sales_order_id']);
                    if (!$so) return;

                    if ($data['tindakan'] === 'cancel') {
                        $so->update(['status' => 'cancelled']);
                        \Filament\Notifications\Notification::make()
                            ->title('Sales Order Berhasil Dibatalkan')
                            ->danger()
                            ->send();
                    } else {
                        TallySheet::create([
                            'sales_order_id' => $so->id,
                            'tally_number' => 'TL-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
                            'tally_date' => $data['tally_date'],
                            'operator_id' => auth()->id(),
                            'status' => 'DRAFT',
                        ]);

                        $so->update(['status' => 'processing']);

                        \Filament\Notifications\Notification::make()
                            ->title('Tally Sheet Berhasil Dibuat')
                            ->success()
                            ->send();
                    }

                    // POIN 1: DIALIHKAN KE INDEX (Refresh halaman tetap di list utama)
                    return redirect()->to(TallySheetResource::getUrl('index'));
                }),
        ];
    }
}
