<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\User;
use App\Support\LegacySqlDump;
use App\Support\LooseName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * PO SAPI HIDUP legacy (`pocattle` + `pocattledetail`) -> `purchase_cattles` + `purchase_cattle_items`, SELURUH riwayat yang tidak dihapus.
 * Langkah 1 dari kelompok sapi: PO sapi -> receive -> weighing -> karkas -> boning (keputusan Ayah, 21 Oktober 2026).
 * Dry-run bawaan; `--apply` menulis; aman diulang (nomor PO yang sudah ada dilewati); satu penolakan membatalkan semuanya.
 *
 * Pemasangan:
 *  - document_number = nopo apa adanya (kembar diberi akhiran -2, ...); supplier lewat peta penggabungan;
 *  - shipping_date = arrival_date (perkiraan kedatangan); summary_note = note; tanggal dibuat = podate (jam dari creatime);
 *  - created_by = createby bila akun ada, selain itu `--fallback-user`;
 *  - item: kelas sapi dicocokkan menurut NAMA (huruf besar, spasi, dan tanda baca diabaikan: "NonPREMIUM" = NONPREMIUM), qty, harga, catatan apa adanya;
 *  - baris detail dan PO yang dihapus (`is_deleted` bukan 0) diabaikan. PO sapi tidak punya status di aplikasi baru.
 */
class ImportLegacyCattlePos extends Command
{
    protected $signature = 'legacy:import-cattle-pos
                            {--source= : Berkas snapshot SQL (legacy/tarik/tarik.sh sapi)}
                            {--fallback-user= : Username akun pengganti untuk pembuat PO yang tidak ada di aplikasi}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}
                            {--examples=2 : Jumlah contoh pasangan lama -> baru}';

    protected $description = 'Pindahkan seluruh riwayat PO sapi legacy ke purchase_cattles (dry-run bawaan)';

    public function handle(): int
    {
        $path = $this->option('source');

        if (! $path || ! is_file($path)) {
            $this->error('--source wajib diisi dengan berkas snapshot SQL yang ada.');

            return self::FAILURE;
        }

        $fallback = $this->option('fallback-user') ? User::withoutGlobalScopes()->where('username', $this->option('fallback-user'))->first() : null;

        if (! $fallback) {
            $this->error('--fallback-user wajib diisi dengan username yang ada.');

            return self::FAILURE;
        }

        $sql = file_get_contents($path);
        $apply = (bool) $this->option('apply');
        $table = fn (string $name) => collect(LegacySqlDump::parseTable($sql, $name));

        $classes = DB::table('cattle_classes')->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [LooseName::key($name) => $id]);
        $supplierIds = Supplier::query()->pluck('id')->flip();
        $userIds = User::withoutGlobalScopes()->pluck('id')->flip();
        $existing = DB::table('purchase_cattles')->pluck('document_number')->flip();
        $details = $table('pocattledetail')->filter(fn ($r) => (int) $r['is_deleted'] === 0)->groupBy('idpo');
        $rows = $table('pocattle')->filter(fn ($r) => (int) $r['is_deleted'] === 0)->sortBy('idpo')->values();

        $stats = ['created' => 0, 'skipped' => 0, 'linesIn' => 0, 'linesOut' => 0, 'heads' => 0.0, 'value' => 0.0, 'merged' => 0, 'duplicates' => 0, 'fallback' => 0, 'noItems' => 0];
        $refused = $conflicts = $examples = $perClass = [];
        $seen = [];

        $run = function () use ($rows, $details, $classes, $supplierIds, $userIds, $existing, $fallback, $apply, &$stats, &$refused, &$conflicts, &$examples, &$perClass, &$seen): void {
            foreach ($rows as $row) {
                $number = trim((string) $row['nopo']);
                $note = trim((string) ($row['note'] ?? ''));

                $seen[$number] = ($seen[$number] ?? 0) + 1;

                if ($seen[$number] > 1) {
                    $note = trim($note." | Nomor kembar di legacy (idpo={$row['idpo']}): nomor asli {$number}", ' |');
                    $number .= '-'.$seen[$number];
                    $stats['duplicates']++;
                }

                if (isset($existing[$number])) {
                    $stats['skipped']++;

                    continue;
                }

                $supplierId = ImportLegacyMaster::SUPPLIER_MERGES[(int) $row['idsupplier']] ?? (int) $row['idsupplier'];

                if (! isset($supplierIds[$supplierId])) {
                    $refused[] = "{$number}: supplier idsupplier={$row['idsupplier']} tidak ada -- DITOLAK.";

                    continue;
                }

                if ($supplierId !== (int) $row['idsupplier']) {
                    $stats['merged']++;
                }

                $userId = (int) $row['createby'];

                if (! isset($userIds[$userId])) {
                    $note = trim($note.' | Pembuat legacy: iduser '.$userId, ' |');
                    $userId = $fallback->id;
                    $stats['fallback']++;
                }

                $items = [];

                foreach ($details->get($row['idpo'], collect()) as $line) {
                    $stats['linesIn']++;
                    $classId = $classes[LooseName::key($line['class'])] ?? null;

                    if ($classId === null) {
                        $refused[] = "{$number}: kelas sapi \"{$line['class']}\" tidak dikenal -- DITOLAK.";

                        continue;
                    }

                    $qty = (int) round((float) $line['qty']);
                    $price = (float) $line['price'];
                    $items[] = [
                        'cattle_class_id' => $classId,
                        'qty' => $qty,
                        'price' => $price,
                        'item_notes' => trim((string) ($line['notes'] ?? '')) ?: null,
                        'created_by' => $userId,
                    ];
                    $stats['linesOut']++;
                    $stats['heads'] += $qty;
                    $stats['value'] += $qty * $price;
                    $perClass[$line['class']] = ($perClass[$line['class']] ?? 0) + 1;
                }

                $stats['noItems'] += $items === [] ? 1 : 0;

                $created = substr((string) $row['podate'], 0, 10).' '.(substr((string) $row['creatime'], 11) ?: '00:00:00');
                $header = [
                    'document_number' => $number,
                    'supplier_id' => $supplierId,
                    'shipping_date' => $row['arrival_date'] ?: substr((string) $row['podate'], 0, 10),
                    'summary_note' => trim($note, ' |') ?: null,
                    'created_by' => $userId,
                ];

                if (count($examples) < (int) $this->option('examples')) {
                    $examples[] = [$row, $details->get($row['idpo'], collect())->all(), $header, $items, $created];
                }

                $stats['created']++;

                if ($apply) {
                    $id = DB::table('purchase_cattles')->insertGetId($header + ['created_at' => $created, 'updated_at' => $created]);

                    foreach ($items as $item) {
                        DB::table('purchase_cattle_items')->insert($item + ['purchase_cattle_id' => $id, 'created_at' => $created, 'updated_at' => $created]);
                    }
                }
            }

            if ($apply && $refused !== []) {
                throw new \RuntimeException('refused');
            }
        };

        try {
            $apply ? DB::transaction($run) : $run();
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'refused') {
                throw $e;
            }
        }

        $this->report($apply, $stats, $refused, $conflicts, $perClass, $examples);

        return $refused === [] ? self::SUCCESS : self::FAILURE;
    }

    private function report(bool $apply, array $stats, array $refused, array $conflicts, array $perClass, array $examples): void
    {
        $this->line($apply ? ($refused === [] ? 'DITERAPKAN (--apply)' : 'DITOLAK -- tidak ada yang ditulis.') : 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.');
        $this->line("purchase_cattles -- dibuat: {$stats['created']}, sudah ada (dilewati): {$stats['skipped']}, konflik: ".count($conflicts).', DITOLAK: '.count($refused));
        $this->line("Baris kelas: {$stats['linesOut']} dibawa dari {$stats['linesIn']}; qty total ".number_format($stats['heads'], 0, ',', '.').'; nilai (qty x harga) '.number_format($stats['value'], 0, ',', '.').". PO tanpa baris: {$stats['noItems']}.");
        $this->line('Per kelas: '.collect($perClass)->map(fn ($n, $k) => "{$k}={$n}")->implode(', '));
        $this->line("Supplier lewat peta penggabungan: {$stats['merged']}. Pembuat diganti akun pengganti: {$stats['fallback']}. Nomor kembar diberi akhiran: {$stats['duplicates']}.");

        foreach ($examples as [$row, $lines, $header, $items, $created]) {
            $this->line('');
            $this->line("Contoh {$row['nopo']}:");
            $this->line("  LAMA  pocattle: nopo={$row['nopo']} podate={$row['podate']} arrival_date={$row['arrival_date']} idsupplier={$row['idsupplier']} createby={$row['createby']} note=\"".($row['note'] ?? '').'"');

            foreach (array_slice($lines, 0, 2) as $line) {
                $this->line("        pocattledetail: class={$line['class']} qty={$line['qty']} price={$line['price']}");
            }

            $this->line("  BARU  purchase_cattles: document_number={$header['document_number']} supplier_id={$header['supplier_id']} shipping_date={$header['shipping_date']} dibuat={$created}");

            foreach (array_slice($items, 0, 2) as $item) {
                $this->line("        item: cattle_class_id={$item['cattle_class_id']} qty={$item['qty']} price={$item['price']}");
            }
        }

        $this->line('');
        $this->line('Catatan: PO sapi tidak punya status di aplikasi baru. Baris dan PO yang dihapus di legacy diabaikan.');

        foreach (array_slice($refused, 0, 10) as $line) {
            $this->line("  ! {$line}");
        }
    }
}
