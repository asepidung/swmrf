<?php

namespace App\Console\Commands;

use App\Models\CustomerGroup;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import harga terbaru per grup dari berkas xlsx hasil tarikan read-only DB
 * produksi legacy (issue #532, keputusan Owner 13 Oktober 2026), supaya costing
 * boning bisa dicoba dengan angka sungguhan.
 *
 * Berkasnya (`legacy/harga-terbaru-per-grup-2026-10-13.xlsx`) berisi harga dan
 * di-gitignore -- JANGAN pernah masuk repositori. Sheet: LION, HPM, UMUM; kolom
 * pertama nama produk, kedua harga terbaru, ketiga tanggal, keempat customer.
 *
 * Yang dikerjakan:
 *  1. Grup LION (trading terms 6%, acuan costing), HYPERMART (sheet HPM, 16,45%,
 *     acuan costing), UMUM (harga umum, potongan 0%). Dicocokkan menurut nama; dibuat
 *     bila belum ada; perbedaan terms/penanda pada grup yang sudah ada dilaporkan
 *     dan diperbaiki.
 *  2. Price list per grup (satu per grup); item = harga terbaru per produk. Produk
 *     dicocokkan ke master menurut nama ternormalisasi (huruf besar, spasi
 *     dirapikan); yang tidak ketemu DILAPORKAN, tidak ditebak.
 *  3. LION: Silverside, Rump, Chuck, Blade, Chuck Tender memakai harga rata LION
 *     terbaru (bawaan 147.500) -- LION memberi harga rata untuk hampir semua
 *     potongan, nama di SO berubah-ubah sesuai permintaan LION. Ditandai di catatan
 *     item; harga di berkas untuk produk itu (bila ada) diganti dan dilaporkan.
 *
 *  4. Acuan costing per produk (`products.costing_customer_group_id`): sumbernya BUKAN
 *     "produk yang muncul di sheet" (dijual ke LION != dinilai memakai harga LION),
 *     melainkan warna sel Costing Juni (`hpp.md` §7): 6% -> LION, 16,45% -> HYPERMART,
 *     sisanya kosong (harga umum). Pemetaannya data di `REFERENCE_PRODUCTS`, dicocokkan
 *     menurut nama ternormalisasi; yang tidak ketemu dilaporkan. Acuan yang sudah diisi
 *     tidak ditimpa kecuali `--overwrite`. Baris 5% di Excel (`UNMAPPED_5_PERCENT`) belum
 *     punya grup dan hanya dilaporkan.
 *
 * Default-nya laporan saja (dry-run). Idempoten: dijalankan ulang (mis. sesudah
 * `legacy:import-master` mengisi produk yang tadinya belum ada) hanya menambah
 * yang belum ada. Item yang sudah ada dengan harga BERBEDA dilaporkan dan tidak
 * ditimpa -- harga yang diubah lewat layar tidak hilang -- kecuali `--overwrite`.
 */
class ImportLegacyPrices extends Command
{
    protected $signature = 'legacy:import-prices
                            {--source= : Path ke berkas xlsx harga (bawaan: legacy/harga-terbaru-per-grup-2026-10-13.xlsx)}
                            {--apply : Benar-benar menulis perubahan (default hanya laporan dry-run)}
                            {--overwrite : Timpa harga item yang sudah ada dan berbeda dengan berkas}
                            {--create-groups : Boleh membuat grup LION/HYPERMART bila belum ada (id baru, BUKAN id legacy)}
                            {--lion-average=147500 : Harga rata LION untuk Silverside, Rump, Chuck, Blade, Chuck Tender}';

    protected $description = 'Impor grup (LION/HYPERMART/UMUM), trading terms, dan price list dari harga SO legacy (xlsx)';

    public const DEFAULT_SOURCE = 'legacy/harga-terbaru-per-grup-2026-10-13.xlsx';

    /**
     * Grup yang dibuat/dicocokkan. `sheet` = nama sheet di berkas.
     *
     * `aliases`: nama lain grup yang sama di master (legacy menamai HYPERMART "HPM").
     *
     * @var array<string, array{sheet: string, terms: float, costing: bool, general: bool, aliases: array<int, string>}>
     */
    public const GROUPS = [
        'LION' => ['sheet' => 'LION', 'terms' => 6.0, 'costing' => true, 'general' => false, 'aliases' => []],
        'HYPERMART' => ['sheet' => 'HPM', 'terms' => 16.45, 'costing' => true, 'general' => false, 'aliases' => ['HPM']],
        'UMUM' => ['sheet' => 'UMUM', 'terms' => 0.0, 'costing' => false, 'general' => true, 'aliases' => []],
    ];

    /**
     * Produk acuan LION yang memakai harga rata. Tiap baris = satu produk dengan
     * ejaan yang diterima.
     *
     * @var array<int, array<int, string>>
     */
    public const LION_AVERAGE_PRODUCTS = [
        ['SILVERSIDE'],
        ['RUMP'],
        ['CHUCK'],
        ['BLADE'],
        ['CHUCK TENDER', 'CHUCKTENDER'],
    ];

    /**
     * Acuan costing per produk (`hpp.md` §7, warna sel Costing Juni): grup => daftar
     * produk, tiap produk dengan ejaan yang diterima (ejaan di tabel potongan dan
     * ejaan di SO legacy).
     *
     * @var array<string, array<int, array<int, string>>>
     */
    public const REFERENCE_PRODUCTS = [
        'LION' => [
            ['TOPSIDE'], ['SILVERSIDE'], ['KNUCKLE'], ['TENDERLOIN'], ['STRIPLOIN CUT'], ['RUMP'], ['CHUCK'], ['BLADE'],
            ['CHUCK TENDER', 'CHUCKTENDER'], ['SHANK'], ['BRISKET'], ['FQ 85 CL', 'FQ 85 CL CUT'],
            ['SHORT RIB', 'SHORTRIB'], ['MARROW BONE', 'MARROWBONE'], ['OXTAIL'],
        ],
        'HYPERMART' => [
            ['STRIPLOIN WHOLE'], ['STRIPLOIN GOLD'], ['STRIPLOIN LESS FAT'], ['CUBEROLL', 'CUBE ROLL'], ['CUBEROLL TS', 'CUBE ROLL TS'],
        ],
    ];

    /**
     * Potongan 5% di Excel (`hpp.md` §7) yang belum punya grupnya: dibiarkan kosong
     * (harga umum) dan hanya dilaporkan.
     *
     * @var array<int, string>
     */
    public const UNMAPPED_5_PERCENT = ['BRISKET PEDO', 'RIBS', 'FAT BRISKET'];

    /**
     * Ejaan yang menyebut produk yang SAMA. Bila berkas hanya memuat salah satu ejaan di sebuah grup
     * (mis. LION hanya "FQ 85 CL CUT"), harganya dipakai juga untuk produk master berejaan lain di
     * grup itu yang belum punya baris sendiri -- baris berkas yang menyebut produk itu dengan namanya
     * sendiri tetap menang. FQ 85 CL CUT selalu digabung ke FQ 85 CL (`hpp.md` §6).
     *
     * @var array<int, array<int, string>>
     */
    public const SAME_PRODUCT_SPELLINGS = [
        ['FQ 85 CL', 'FQ 85 CL CUT'],
        ['CHUCK TENDER', 'CHUCKTENDER'],
        ['SHORT RIB', 'SHORTRIB'],
        ['MARROW BONE', 'MARROWBONE'],
        ['CUBEROLL', 'CUBE ROLL'],
        ['CUBEROLL TS', 'CUBE ROLL TS'],
    ];

    /** @var array<int, string> */
    private array $referenceLines = [];

    /** @var array<int, string> */
    private array $groupLines = [];

    /** @var array<string, array{create: array<int, string>, same: array<int, string>, differs: array<int, string>, updated: array<int, string>}> */
    private array $itemLines = [];

    /** @var array<string, array<int, string>> */
    private array $unmatched = [];

    /** @var array<int, string> */
    private array $notes = [];

    public function handle(): int
    {
        $source = $this->option('source') ?: base_path(self::DEFAULT_SOURCE);

        if (! is_file($source)) {
            $this->error("Berkas tidak ditemukan: {$source}");

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $sheets = $this->readSheets($source);

        $missingSheets = array_diff(array_column(self::GROUPS, 'sheet'), array_keys($sheets));
        if ($missingSheets !== []) {
            $this->error('Sheet tidak ada di berkas: '.implode(', ', $missingSheets));

            return self::FAILURE;
        }

        $products = $this->productMap();

        // Grup LION dan HYPERMART (legacy: HPM) datang dari master legacy DENGAN id legacy
        // (`legacy:import-master`). Membuatnya di sini memberi id baru dan bisa menabrak
        // id legacy yang diimpor kemudian -- jadi dihentikan kecuali --create-groups.
        // UMUM bukan grup legacy; dibuat di id berikutnya.
        if (! $this->option('create-groups')) {
            $missing = [];
            foreach (self::GROUPS as $key => $config) {
                if ($config['costing'] && ! $this->findGroup($key, $config)) {
                    $missing[] = $key;
                }
            }

            if ($missing !== []) {
                $this->error('Grup belum ada di master: '.implode(', ', $missing).'. Jalankan legacy:import-master (grup) lebih dulu supaya id legacy dipertahankan, atau pakai --create-groups (id baru).');

                return self::FAILURE;
            }
        }

        DB::beginTransaction();

        try {
            $groups = [];

            foreach (self::GROUPS as $key => $config) {
                $group = $this->ensureGroup($key, $config, $apply);
                $groups[$key] = $group;
                $desired = $this->desiredItems($key, $sheets[$config['sheet']], $products);

                $this->applyItems($key, $group, $desired, $apply);
            }

            $this->assignReferences($groups, $products);

            $apply ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->printReport($apply);

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<int, array{name: string, price: int, date: string, customer: string}>>
     */
    private function readSheets(string $path): array
    {
        $reader = new \OpenSpout\Reader\XLSX\Reader();
        $reader->open($path);
        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];

            foreach ($sheet->getRowIterator() as $number => $row) {
                if ($number === 1) {
                    continue;
                }

                $cells = array_map(fn ($cell) => $cell->getValue(), $row->getCells());
                $name = $this->normalize((string) ($cells[0] ?? ''));

                if ($name === '' || ! is_numeric($cells[1] ?? null)) {
                    continue;
                }

                $date = $cells[2] ?? '';
                $rows[] = [
                    'name' => $name,
                    'price' => (int) round((float) $cells[1]),
                    'date' => $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date,
                    'customer' => trim((string) ($cells[3] ?? '')),
                ];
            }

            $sheets[$sheet->getName()] = $rows;
        }

        $reader->close();

        return $sheets;
    }

    /**
     * Semua produk master yang namanya salah satu ejaan ini. Beberapa ejaan di master bisa jadi
     * produk TERPISAH (mis. FQ 85 CL dan FQ 85 CL CUT) -- semuanya diperlakukan sebagai produk
     * yang sama menurut namanya.
     *
     * @param  array<int, string>  $spellings
     * @param  array<string, int>  $products
     * @return array<int, int>
     */
    private function productsFor(array $spellings, array $products): array
    {
        $ids = [];

        foreach ($spellings as $spelling) {
            if (isset($products[$spelling])) {
                $ids[$products[$spelling]] = $products[$spelling];
            }
        }

        return array_values($ids);
    }

    /** Huruf besar dan spasi dirapikan -- satu-satunya normalisasi (tidak menebak ejaan lain). */
    private function normalize(string $value): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $value)));
    }

    /** @return array<string, int> nama ternormalisasi => product id */
    private function productMap(): array
    {
        $map = [];

        foreach (Product::orderBy('id')->get(['id', 'name']) as $product) {
            $key = $this->normalize($product->name);

            if (isset($map[$key])) {
                $this->notes[] = "Nama produk kembar di master setelah dinormalisasi: {$key} (id {$map[$key]} dipakai, id {$product->id} diabaikan).";

                continue;
            }

            $map[$key] = $product->id;
        }

        return $map;
    }

    /** Grup yang sudah ada, dicocokkan menurut nama ternormalisasi atau nama lainnya (alias). */
    private function findGroup(string $key, array $config): ?CustomerGroup
    {
        $names = array_map(fn (string $name) => $this->normalize($name), [$key, ...$config['aliases']]);

        return CustomerGroup::orderBy('id')->get()->first(fn (CustomerGroup $g) => in_array($this->normalize($g->name), $names, true));
    }

    /**
     * Grup dicocokkan menurut nama; dibuat bila belum ada. Terms dan penanda yang
     * berbeda dilaporkan lalu diperbaiki (hanya bila --apply).
     *
     * @param  array{sheet: string, terms: float, costing: bool, general: bool}  $config
     */
    private function ensureGroup(string $key, array $config, bool $apply): ?CustomerGroup
    {
        $group = $this->findGroup($key, $config);

        if ($config['general']) {
            $other = CustomerGroup::where('is_general_price_reference', true)
                ->when($group, fn ($q) => $q->where('id', '!=', $group->id))
                ->first();

            if ($other) {
                $this->groupLines[] = "! {$key}: grup {$other->name} sudah ditandai harga umum; penanda tidak dipindahkan, selesaikan manual.";
                $config['general'] = false;
            }
        }

        if (! $group) {
            $this->groupLines[] = "+ {$key}: dibuat (terms {$config['terms']}%, acuan costing: ".($config['costing'] ? 'ya' : 'tidak').', harga umum: '.($config['general'] ? 'ya' : 'tidak').').';

            return CustomerGroup::create([
                'name' => $key,
                'trading_terms_percent' => $config['terms'],
                'is_costing_reference' => $config['costing'],
                'is_general_price_reference' => $config['general'],
            ]);
        }

        $changes = [];
        if ((float) $group->trading_terms_percent !== $config['terms']) {
            $changes['trading_terms_percent'] = $config['terms'];
        }
        if ((bool) $group->is_costing_reference !== $config['costing']) {
            $changes['is_costing_reference'] = $config['costing'];
        }
        if ($config['general'] && ! $group->is_general_price_reference) {
            $changes['is_general_price_reference'] = true;
        }

        if ($changes === []) {
            $this->groupLines[] = "= {$key}: sudah ada dan sesuai (id {$group->id}).";

            return $group;
        }

        $this->groupLines[] = "~ {$key}: sudah ada (id {$group->id}), diperbaiki: ".json_encode($changes).'.';
        $group->update($changes);

        return $group;
    }

    /**
     * Harga yang SEHARUSNYA ada di price list grup ini, per produk master.
     *
     * @param  array<int, array{name: string, price: int, date: string, customer: string}>  $rows
     * @param  array<string, int>  $products
     * @return array<int, array{price: int, note: string}>
     */
    private function desiredItems(string $key, array $rows, array $products): array
    {
        $desired = [];
        $fromSheet = [];

        foreach ($rows as $row) {
            if (! isset($products[$row['name']])) {
                $this->unmatched[$key][] = "{$row['name']} (Rp ".number_format($row['price'], 0, ',', '.').')';

                continue;
            }

            $productId = $products[$row['name']];
            $fromSheet[$productId] = $row;
            $desired[$productId] = [
                'price' => $row['price'],
                'note' => "Import harga SO terbaru {$row['date']}".($row['customer'] !== '' ? " ({$row['customer']})" : ''),
            ];
        }

        // Ejaan lain dari produk yang sama mengikuti harga yang ada di berkas (yang punya baris sendiri tetap menang).
        foreach (self::SAME_PRODUCT_SPELLINGS as $spellings) {
            $members = $this->productsFor($spellings, $products);
            $source = null;

            foreach ($members as $memberId) {
                if (isset($fromSheet[$memberId])) {
                    $source = $fromSheet[$memberId];

                    break;
                }
            }

            if ($source === null) {
                continue;
            }

            foreach ($members as $memberId) {
                if (! isset($desired[$memberId])) {
                    $desired[$memberId] = [
                        'price' => $source['price'],
                        'note' => "Mengikuti {$source['name']} (ejaan lain, produk yang sama) -- import harga SO terbaru {$source['date']}",
                    ];
                    $this->notes[] = "{$key}: produk master berejaan lain mengikuti harga {$source['name']} Rp ".number_format($source['price'], 0, ',', '.').'.';
                }
            }
        }

        if ($key === 'LION') {
            $average = (int) $this->option('lion-average');

            foreach (self::LION_AVERAGE_PRODUCTS as $spellings) {
                $found = $this->productsFor($spellings, $products);

                if ($found === []) {
                    $this->unmatched['LION (harga rata)'][] = $spellings[0].' -- belum ada di master; jalankan ulang setelah legacy:import-master';

                    continue;
                }

                foreach ($found as $productId) {
                    if (isset($fromSheet[$productId]) && $fromSheet[$productId]['price'] !== $average) {
                        $this->notes[] = "LION {$fromSheet[$productId]['name']}: berkas Rp ".number_format($fromSheet[$productId]['price'], 0, ',', '.')." tgl {$fromSheet[$productId]['date']} diganti harga rata LION Rp ".number_format($average, 0, ',', '.').'.';
                    }

                    $desired[$productId] = ['price' => $average, 'note' => 'Harga rata LION (keputusan Owner 13 Okt 2026)'];
                }
            }
        }

        return $desired;
    }

    /**
     * @param  array<int, array{price: int, note: string}>  $desired
     */
    private function applyItems(string $key, ?CustomerGroup $group, array $desired, bool $apply): void
    {
        $lines = ['create' => [], 'same' => [], 'differs' => [], 'updated' => []];
        $names = Product::whereIn('id', array_keys($desired))->pluck('name', 'id');

        $priceList = $group ? PriceList::where('customer_group_id', $group->id)->first() : null;

        if ($group && ! $priceList) {
            $priceList = PriceList::create(['customer_group_id' => $group->id]);
            $this->groupLines[] = "+ {$key}: price list dibuat.";
        }

        $existing = $priceList ? $priceList->items()->pluck('price', 'product_id') : collect();

        foreach ($desired as $productId => $item) {
            $label = ($names[$productId] ?? "#{$productId}").' Rp '.number_format($item['price'], 0, ',', '.');

            if (! $existing->has($productId)) {
                $lines['create'][] = $label;
                $priceList?->items()->create(['product_id' => $productId, 'price' => $item['price'], 'note' => $item['note']]);

                continue;
            }

            if ((int) $existing[$productId] === $item['price']) {
                $lines['same'][] = $label;

                continue;
            }

            $current = 'Rp '.number_format((int) $existing[$productId], 0, ',', '.');

            if ($this->option('overwrite')) {
                $lines['updated'][] = "{$label} (sebelumnya {$current})";
                PriceListItem::where('price_list_id', $priceList->id)->where('product_id', $productId)->update(['price' => $item['price'], 'note' => $item['note']]);
            } else {
                $lines['differs'][] = "{$label} -- sudah ada {$current}, tidak ditimpa (pakai --overwrite)";
            }
        }

        $this->itemLines[$key] = $lines;
    }

    /**
     * Isi `products.costing_customer_group_id` menurut `REFERENCE_PRODUCTS`.
     * Acuan yang sudah diisi (sama: dilewati; beda: dilaporkan) tidak ditimpa kecuali
     * `--overwrite`.
     *
     * @param  array<string, CustomerGroup|null>  $groups
     * @param  array<string, int>  $products
     */
    private function assignReferences(array $groups, array $products): void
    {
        foreach (self::REFERENCE_PRODUCTS as $key => $list) {
            $group = $groups[$key] ?? null;

            foreach ($list as $spellings) {
                $found = $this->productsFor($spellings, $products);

                if ($found === []) {
                    $this->unmatched["Acuan costing {$key}"][] = $spellings[0].' -- belum ada di master; jalankan ulang setelah legacy:import-master';

                    continue;
                }

                foreach ($found as $productId) {
                    $product = Product::find($productId);

                    if ((int) $product->costing_customer_group_id === (int) $group?->id && $group) {
                        $this->referenceLines[] = "= {$product->name}: acuan {$key} sudah terisi.";

                        continue;
                    }

                    if ($product->costing_customer_group_id && ! $this->option('overwrite')) {
                        $current = CustomerGroup::find($product->costing_customer_group_id)?->name ?? "#{$product->costing_customer_group_id}";
                        $this->referenceLines[] = "! {$product->name}: acuan sudah {$current}, tidak ditimpa (pakai --overwrite) -- seharusnya {$key}.";

                        continue;
                    }

                    $this->referenceLines[] = "+ {$product->name}: acuan costing {$key}.";
                    $product->update(['costing_customer_group_id' => $group->id]);
                }
            }
        }

        foreach (self::UNMAPPED_5_PERCENT as $name) {
            if (isset($products[$name])) {
                $this->notes[] = "{$name}: potongan 5% di Excel, belum ada grupnya -- acuan dibiarkan kosong (harga umum).";
            }
        }
    }

    private function printReport(bool $apply): void
    {
        $this->newLine();
        $this->info($apply ? 'Diterapkan (--apply):' : 'Dry-run -- tidak ada yang ditulis. Tambahkan --apply untuk benar-benar menulis.');
        $this->newLine();

        $this->line('<fg=cyan>Grup</>');
        foreach ($this->groupLines as $line) {
            $this->line("  {$line}");
        }

        foreach ($this->itemLines as $key => $lines) {
            $this->newLine();
            $this->line("<fg=cyan>Price list {$key}</> -- ditambah: ".count($lines['create']).', sama: '.count($lines['same']).', berbeda (dilewati): '.count($lines['differs']).', ditimpa: '.count($lines['updated']));

            foreach ($lines['create'] as $line) {
                $this->line("  <fg=green>+ {$line}</>");
            }
            foreach ($lines['updated'] as $line) {
                $this->line("  <fg=blue>~ {$line}</>");
            }
            foreach ($lines['differs'] as $line) {
                $this->line("  <fg=yellow>! {$line}</>");
            }
        }

        $this->newLine();
        $this->line('<fg=cyan>Acuan costing produk</>');
        foreach ($this->referenceLines as $line) {
            $this->line("  {$line}");
        }

        foreach ($this->unmatched as $key => $names) {
            $this->newLine();
            $this->line("<fg=yellow>Tidak ada di master -- {$key}</> (".count($names).'), tidak ditebak:');
            foreach ($names as $name) {
                $this->line("  ? {$name}");
            }
        }

        if ($this->notes !== []) {
            $this->newLine();
            $this->info('Catatan:');
            foreach ($this->notes as $note) {
                $this->line("  - {$note}");
            }
        }
    }
}
