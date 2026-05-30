<?php

namespace App\Filament\Resources\CarcassResource\Pages;

use App\Filament\Resources\CarcassResource;
use App\Models\Carcass;
use App\Models\CattleWeighing;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class CreateCarcass extends CreateRecord
{
    protected static string $resource = CarcassResource::class;

    // INI OBAT BUAT NGILANGIN "CREATE & CREATE ANOTHER"
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    public function mount(): void
    {
        // Harus dipanggil duluan di Filament v3
        parent::mount();

        $weighId = request()->query('weigh_id');
        if (!$weighId) {
            $this->redirect($this->getResource()::getUrl('draft'));
            return;
        }

        // Tarik data timbangan beserta relasinya
        $weighing = CattleWeighing::with(['items.receivingItem', 'items.carcassItem'])->find($weighId);

        if ($weighing) {
            $items = [];
            foreach ($weighing->items as $item) {
                // KUNCI: SKIP/LEWATI sapi yang sudah ada datanya di tabel carcass_items
                if ($item->carcassItem) {
                    continue;
                }

                $items[(string) Str::uuid()] = [
                    'cattle_weighing_item_id' => $item->id,
                    'eartag_display' => $item->receivingItem->eartag ?? '-',
                    'weight_display' => $item->weight,
                    'carcass_1' => null,
                    'carcass_2' => null,
                    'hides' => null,
                    'tail' => null,
                ];
            }

            // Suntik ke dalam Form
            $this->form->fill([
                'cattle_weighing_id' => $weighing->id,
                'weigh_no_display' => $weighing->weigh_no,
                'kill_date' => now()->format('Y-m-d'),
                'carcass_items' => $items, // Data array sisa sapi dimasukkan ke repeater
            ]);
        }
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return DB::transaction(function () use ($data) {
            $currentYear2Digit = date('y');
            $currentYear4Digit = date('Y');

            $latest = Carcass::withTrashed()
                ->whereYear('created_at', $currentYear4Digit)
                ->latest('id')
                ->first();

            $urut = 1;
            if ($latest && preg_match('/CRS-' . $currentYear2Digit . '(\d{3,})/', $latest->carcass_no, $matches)) {
                $urut = (int)$matches[1] + 1;
            }
            $carcassNo = 'CRS-' . $currentYear2Digit . str_pad((string)$urut, 3, '0', STR_PAD_LEFT);

            // Simpan Header
            $carcass = Carcass::create([
                'carcass_no' => $carcassNo,
                'cattle_weighing_id' => $data['cattle_weighing_id'],
                'kill_date' => $data['kill_date'],
                'note' => $data['note'] ?? null,
                'created_by' => (int) Auth::id(),
            ]);

            // KUNCI: Filter & Simpan Detail
            if (isset($data['carcass_items'])) {
                foreach ($data['carcass_items'] as $item) {
                    $c1 = (float)($item['carcass_1'] ?? 0);
                    $c2 = (float)($item['carcass_2'] ?? 0);
                    $hides = (float)($item['hides'] ?? 0);
                    $tail = (float)($item['tail'] ?? 0);

                    // Kalau ada isinya (gak kosong), baru disave ke database!
                    if ($c1 > 0 || $c2 > 0 || $hides > 0 || $tail > 0) {
                        $carcass->items()->create([
                            'cattle_weighing_item_id' => $item['cattle_weighing_item_id'],
                            'carcass_1' => $c1,
                            'carcass_2' => $c2,
                            'hides' => $hides,
                            'tail' => $tail,
                        ]);
                    }
                }
            }

            return $carcass;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
