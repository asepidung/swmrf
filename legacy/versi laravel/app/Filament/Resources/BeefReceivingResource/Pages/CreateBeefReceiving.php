<?php

namespace App\Filament\Resources\BeefReceivingResource\Pages;

use App\Filament\Resources\BeefReceivingResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\BeefReceiving;
use App\Models\BeefPurchaseOrder;
use App\Models\BeefReceivingItem;
use App\Models\AccountPayable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class CreateBeefReceiving extends CreateRecord
{
    protected static string $resource = BeefReceivingResource::class;

    // MATIKAN TOMBOL "CREATE & CREATE ANOTHER"
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    public function mount(): void
    {
        $poId = request()->query('po_id');

        if (!$poId) {
            $this->redirect($this->getResource()::getUrl('index'));
            return;
        }

        parent::mount();

        // Tarik data PO Beef beserta item & suppliernya
        $po = BeefPurchaseOrder::with(['items.item', 'supplier'])->find($poId);

        if ($po) {
            $tempItems = [];

            foreach ($po->items as $poItem) {
                // Hitung yang sudah diterima sebelumnya
                $received = BeefReceivingItem::whereHas('receiving', function ($q) use ($poId) {
                    $q->where('beef_purchase_order_id', $poId);
                })
                    ->where('beef_item_id', $poItem->product_id)
                    ->sum('qty_received');

                // Sisa yang harus dikirim
                $sisa = $poItem->qty - $received;

                if ($sisa > 0) {
                    $tempItems[] = [
                        'beef_item_id'  => $poItem->product_id,
                        'item_name'     => $poItem->item->name ?? 'Unknown',
                        'price'         => $poItem->price, // Harga asli PO
                        'qty_remaining' => $sisa,
                        'qty_received'  => 0, // Default 0 biar user isi sendiri
                    ];
                }
            }

            // Lempar data ke form
            $this->form->fill([
                'beef_purchase_order_id' => $po->id,
                'supplier_id'            => $po->supplier_id,
                'po_number_display'      => $po->po_number,
                'supplier_name_display'  => $po->supplier->name,
                'receive_date'           => now(),
                'tempItems'              => $tempItems,
            ]);
        }
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Langsung return hasil dari transaksi DB
        return DB::transaction(function () use ($data) {

            // 1. Bikin Nomor GR Otomatis (Format: GRB#YY001)
            $currentYear = date('Y');
            $currentYear2Digit = date('y');

            // Cari GR terakhir di tahun yang sama biar nomor urutnya rapi
            $latestGR = BeefReceiving::whereYear('created_at', $currentYear)->latest('id')->first();

            $urut = 1;
            if ($latestGR && preg_match('/GRB#' . $currentYear2Digit . '(\d{3,})/', $latestGR->receiving_number, $matches)) {
                // Ambil 3 angka terakhir dari nomor GR sebelumnya, lalu tambah 1
                $urut = (int)$matches[1] + 1;
            }

            // Rakit nomor GR-nya
            $grNumber = 'GRB#' . $currentYear2Digit . str_pad($urut, 3, '0', STR_PAD_LEFT);

            // 2. Simpan Induk GR
            $receiving = BeefReceiving::create([
                'receiving_number'       => $grNumber,
                'beef_purchase_order_id' => $data['beef_purchase_order_id'],
                'supplier_id'            => $data['supplier_id'],
                'receive_date'           => $data['receive_date'],
                'sj_number'              => $data['sj_number'] ?? null,
                'note'                   => $data['note'] ?? null,
                'created_by'             => Auth::id(),
            ]);

            $semuaBarangLunas = true;

            // Tarik PO untuk cek status
            $po = BeefPurchaseOrder::find($data['beef_purchase_order_id']);

            // 3. Simpan Detail Item
            foreach ($data['tempItems'] as $item) {
                $qtyReceived = (float) $item['qty_received'];

                if ($qtyReceived > 0) {
                    $price = (float) $item['price'];
                    $subtotal = $qtyReceived * $price;

                    BeefReceivingItem::create([
                        'beef_receiving_id' => $receiving->id,
                        'beef_item_id'      => $item['beef_item_id'],
                        'qty_received'      => $qtyReceived,
                        'price'             => $price,
                        'subtotal'          => $subtotal,
                    ]);
                }

                // Cek apakah dengan inputan hari ini, barang sisa jadi habis?
                // Kalau qty yang diinput lebih kecil dari sisa, berarti belum lunas
                if ($qtyReceived < (float) $item['qty_remaining']) {
                    $semuaBarangLunas = false;
                }
            }

            // 4. Integrasi ke Account Payable (Hutang Finance) HANYA JIKA LUNAS
            if ($semuaBarangLunas) {
                // Tarik total keseluruhan dari semua GR yang masuk ke PO ini
                $totalKeseluruhan = BeefReceivingItem::whereHas('receiving', function ($q) use ($po) {
                    $q->where('beef_purchase_order_id', $po->id);
                })->sum('subtotal');

                if ($totalKeseluruhan > 0) {
                    // Jatuh tempo default: +14 Hari
                    $dueDate = \Carbon\Carbon::parse($data['receive_date'])->addDays(14);

                    AccountPayable::create([
                        'payable_id'   => $po->id,
                        'payable_type' => get_class($po),
                        'supplier_id'  => $po->supplier_id,
                        'total_amount' => $totalKeseluruhan,
                        'paid_amount'  => 0,
                        'balance_due'  => $totalKeseluruhan,
                        'status'       => 'UNPAID',
                        'due_date'     => $dueDate,
                        'note'         => "Tagihan PO Beef (Lunas): " . $po->po_number,
                        'created_by'   => Auth::id(),
                    ]);
                }
            }

            // 5. Update Status PO (COMPLETED atau PARTIAL)
            $po->update([
                'status' => $semuaBarangLunas ? 'COMPLETED' : 'PARTIAL'
            ]);

            $pesanNotif = $semuaBarangLunas
                ? 'Barang LUNAS & Tagihan otomatis diteruskan ke Finance.'
                : 'Penerimaan PARTIAL disimpan. PO masih terbuka.';

            Notification::make()
                ->success()
                ->title('GR Berhasil Disimpan!')
                ->body($pesanNotif)
                ->send();

            // Return model $receiving di dalam transaksi
            // Laravel akan meneruskan return ini keluar dari DB::transaction()
            return $receiving;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
