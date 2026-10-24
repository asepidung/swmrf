<?php

namespace App\Console\Commands;

use App\Models\CustomerGroup;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Support\LooseName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Isi price list tiap grup pelanggan dari riwayat Sales Order (keputusan Owner, 23-24 Oktober 2026).
 *
 * Aturan: harga sebuah produk di sebuah grup = harga pada baris SO TERBARU produk itu di antara SEMUA
 * customer grup tersebut (urut tanggal kirim, lalu nomor SO, lalu baris). Customer satu grup berharga
 * sama, jadi harga terbaru customer mana pun mewakili grupnya. Baris berharga 0 dilewati -- kecuali
 * grup GRATISAN, yang memang bernilai 0. SO batal dan yang sudah dihapus tidak dihitung. Produk tanpa
 * riwayat dibiarkan kosong (diisi manual). Grup tanpa customer tidak disentuh.
 *
 * Dijalankan hari-H SESUDAH import SO. Dry-run bawaan; `--apply` menulis. Harga yang sudah ada di price
 * list (mis. dari `legacy:import-prices`, atau diubah lewat layar) tidak ditimpa kecuali `--overwrite`.
 * Aman diulang.
 */
class BuildPriceListsFromSales extends Command
{
    protected $signature = 'pricelist:build-from-sales
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}
                            {--overwrite : Timpa harga item yang sudah ada dan berbeda}
                            {--group= : Hanya grup ini (nama)}';

    protected $description = 'Isi price list per grup pelanggan dari harga SO terbaru (dry-run bawaan, aman diulang)';

    public const FREE_GROUP = 'GRATISAN';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $only = $this->option('group') ? LooseName::key((string) $this->option('group')) : null;
        $total = ['create' => 0, 'same' => 0, 'differs' => 0, 'updated' => 0];

        $groups = CustomerGroup::orderBy('name')->get()
            ->filter(fn (CustomerGroup $g) => $only === null || LooseName::key($g->name) === $only);

        $names = Product::pluck('name', 'id');

        DB::beginTransaction();

        try {
            foreach ($groups as $group) {
                if (! $group->customers()->exists()) {
                    $this->line("<fg=gray>- {$group->name}: tidak punya customer, dilewati.</>");

                    continue;
                }

                $free = LooseName::key($group->name) === LooseName::key(self::FREE_GROUP);
                $latest = $this->latestPrices($group, $free);

                if ($latest === []) {
                    $this->line("<fg=yellow>! {$group->name}: tidak ada riwayat SO berharga, price list diisi manual.</>");

                    continue;
                }

                $counts = $this->write($group, $latest, $names, $apply);

                $this->line("<fg=cyan>{$group->name}</>: produk ".count($latest)." -- ditambah {$counts['create']}, sama {$counts['same']}, berbeda (dilewati) {$counts['differs']}, ditimpa {$counts['updated']}");

                foreach ($counts['lines'] as $line) {
                    $this->line("  {$line}");
                }

                foreach ($counts as $key => $value) {
                    if ($key !== 'lines') {
                        $total[$key] += $value;
                    }
                }
            }

            $apply ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->newLine();
        $this->info(($apply ? 'DITERAPKAN (--apply)' : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.')
            ." | ditambah {$total['create']}, sama {$total['same']}, berbeda {$total['differs']}, ditimpa {$total['updated']}");

        return self::SUCCESS;
    }

    /**
     * Harga terbaru per produk di grup ini.
     *
     * @return array<int, array{price: int, date: string, so: string, customer: string}>
     */
    private function latestPrices(CustomerGroup $group, bool $free): array
    {
        $rows = DB::table('sales_order_items as i')
            ->join('sales_orders as o', 'o.id', '=', 'i.sales_order_id')
            ->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->where('c.customer_group_id', $group->id)
            ->whereNull('o.deleted_at')
            ->where('o.status', '!=', SalesOrder::STATUS_CANCELLED)
            ->when(! $free, fn ($q) => $q->where('i.price', '>', 0))
            ->orderByDesc('o.delivery_date')
            ->orderByDesc('o.id')
            ->orderByDesc('i.id')
            ->get(['i.product_id', 'i.price', 'o.delivery_date', 'o.so_number', 'c.name as customer']);

        $latest = [];

        foreach ($rows as $row) {
            $latest[$row->product_id] ??= [
                'price' => (int) round((float) $row->price),
                'date' => substr((string) $row->delivery_date, 0, 10),
                'so' => (string) $row->so_number,
                'customer' => (string) $row->customer,
            ];
        }

        return $latest;
    }

    /**
     * @param  array<int, array{price: int, date: string, so: string, customer: string}>  $latest
     * @return array{create: int, same: int, differs: int, updated: int, lines: array<int, string>}
     */
    private function write(CustomerGroup $group, array $latest, $names, bool $apply): array
    {
        $counts = ['create' => 0, 'same' => 0, 'differs' => 0, 'updated' => 0, 'lines' => []];

        $priceList = PriceList::where('customer_group_id', $group->id)->first()
            ?? PriceList::create(['customer_group_id' => $group->id]);

        $existing = $priceList->items()->pluck('price', 'product_id');

        foreach ($latest as $productId => $item) {
            $note = "Harga SO {$item['so']} {$item['date']} ({$item['customer']})";
            $label = ($names[$productId] ?? "#{$productId}").' Rp '.number_format($item['price'], 0, ',', '.');

            if (! $existing->has($productId)) {
                $counts['create']++;
                $priceList->items()->create(['product_id' => $productId, 'price' => $item['price'], 'note' => mb_substr($note, 0, 255)]);

                continue;
            }

            if ((int) $existing[$productId] === $item['price']) {
                $counts['same']++;

                continue;
            }

            if ($this->option('overwrite')) {
                $counts['updated']++;
                $priceList->items()->where('product_id', $productId)->update(['price' => $item['price'], 'note' => mb_substr($note, 0, 255)]);
            } else {
                $counts['differs']++;
                $counts['lines'][] = "! {$label} -- sudah ada Rp ".number_format((int) $existing[$productId], 0, ',', '.').', tidak ditimpa (pakai --overwrite)';
            }
        }

        return $counts;
    }
}
