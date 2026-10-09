<?php

namespace App\Filament\Support;

use App\Models\Customer;
use App\Models\Material;
use App\Models\Product;
use App\Models\Supplier;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Dropdown ke master yang TERUS BERTAMBAH -- Product, Material, Supplier,
 * Customer -- dengan pencarian ke SERVER (issue #512, keluhan Owner 8 Oktober
 * 2026).
 *
 * Dulu dropdown semacam ini memuat seluruh pilihan SEKALI saat halaman dibuka
 * (`->options(fn () => X::pluck(...))`, atau `->preload()`), dan `searchable()`
 * hanya menyaring daftar itu di browser. Item yang baru dibuat di tab lain tidak
 * muncul tanpa me-refresh halaman -- dan me-refresh berarti mengisi ulang form
 * dari awal. Sekarang setiap ketikan bertanya ke database:
 *
 *  - tidak peka huruf besar/kecil, cocok ke KODE maupun NAMA;
 *  - hasil dibatasi `LIMIT`, urut nama;
 *  - pilihan BARU hanya yang aktif, tetapi label nilai yang sudah tersimpan
 *    tetap tampil walau recordnya kini nonaktif (`getOptionLabelUsing` tidak
 *    memfilter);
 *  - di dalam Repeater, item yang sudah dipilih di baris lain tidak bisa dipilih
 *    lagi, di layar dan di server (`unique`).
 *
 * Master kecil yang jarang bertambah (gudang, grade, satuan, kategori, ...)
 * TIDAK memakai ini; mereka boleh dimuat sekaligus. Penjaganya:
 * `MasterDropdownGuardTest`.
 */
class MasterSelect
{
    /** Banyak hasil maksimum per pencarian. */
    public const LIMIT = 50;

    public static function material(string $name = 'material_id', bool $activeOnly = true): Select
    {
        return self::server(Select::make($name), Material::class, $activeOnly);
    }

    public static function product(string $name = 'product_id', bool $activeOnly = true): Select
    {
        return self::server(Select::make($name), Product::class, $activeOnly);
    }

    public static function supplier(string $name = 'supplier_id', bool $activeOnly = true): Select
    {
        return self::server(Select::make($name), Supplier::class, $activeOnly);
    }

    public static function customer(string $name = 'customer_id', bool $activeOnly = true): Select
    {
        return self::server(Select::make($name), Customer::class, $activeOnly);
    }

    /** Saringan tabel (`SelectFilter`) yang mencari ke server. */
    public static function filter(SelectFilter $filter, string $model, bool $activeOnly = false): SelectFilter
    {
        return self::server($filter, $model, $activeOnly);
    }

    /**
     * Pasang pencarian ke server pada Select/SelectFilter.
     *
     * @param  Select|SelectFilter  $field
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  Closure|null  $scope  tambahan batasan untuk PILIHAN BARU:
     *                               fn (Builder $query, $livewire, Get $get): void
     */
    public static function server($field, string $model, bool $activeOnly = true, ?Closure $scope = null)
    {
        // Injeksi $livewire/$get hanya bila ada batasan tambahan: keduanya butuh
        // komponen yang sudah terpasang di sebuah form.
        $initial = $scope
            ? fn ($livewire, Get $get): array => self::search($model, '', $activeOnly, self::bind($scope, $livewire, $get))
            : fn (): array => self::search($model, '', $activeOnly);

        $searching = $scope
            ? fn (string $search, $livewire, Get $get): array => self::search($model, $search, $activeOnly, self::bind($scope, $livewire, $get))
            : fn (string $search): array => self::search($model, $search, $activeOnly);

        $field
            ->searchable()
            // Pilihan awal saat dropdown dibuka: LIMIT pertama (urut nama, yang
            // aktif), supaya tidak membuka daftar kosong yang baru terisi setelah
            // mengetik. Mengetik tetap mencari ke SERVER, jadi item yang lahir
            // sesudah halaman dibuka tetap ketemu.
            ->options($initial)
            ->getSearchResultsUsing($searching)
            ->getOptionLabelUsing(fn ($value): ?string => self::label($model, $value))
            // Pilihan ganda (`multiple()`) membaca label nilai terpilihnya lewat sini.
            ->getOptionLabelsUsing(fn (array $values): array => self::labels($model, $values));

        // Pesan prompt hanya ada di Select, tidak di SelectFilter.
        if ($field instanceof Select) {
            $field
                ->searchPrompt(__('Type to search...'))
                ->noSearchResultsMessage(__('Nothing found. If it is new, add it first, then search again here.'));
        }

        return $field;
    }

    /** Membungkus batasan tambahan dengan $livewire dan $get dari komponennya. */
    private static function bind(?Closure $scope, $livewire, Get $get): ?Closure
    {
        return $scope ? fn (Builder $query) => $scope($query, $livewire, $get) : null;
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @return array<int|string, string>
     */
    public static function search(string $model, string $search, bool $activeOnly = true, ?Closure $scope = null): array
    {
        // `!` sebagai karakter escape (ESCAPE '!'), bukan backslash: backslash
        // diperlakukan berbeda oleh MySQL dan SQLite.
        $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($search))).'%';

        return $model::query()
            ->when($activeOnly, fn (Builder $query) => $query->where('is_active', true))
            ->when($scope, fn (Builder $query) => $scope($query))
            ->where(function (Builder $query) use ($term, $model): void {
                $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term]);

                // Product dan Material punya kolom kode; Supplier dan Customer tidak.
                if (Schema::hasColumn((new $model)->getTable(), 'code')) {
                    $query->orWhereRaw("LOWER(code) LIKE ? ESCAPE '!'", [$term]);
                }
            })
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Label beberapa nilai sekaligus (pilihan ganda), tanpa filter aktif.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  array<int, int|string>  $values
     * @return array<int|string, string>
     */
    public static function labels(string $model, array $values): array
    {
        return $model::query()->whereKey($values)->pluck('name', 'id')->all();
    }

    /**
     * Label sebuah nilai yang SUDAH tersimpan -- sengaja tanpa filter aktif,
     * supaya record yang kini nonaktif tetap terbaca di halaman Edit.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    public static function label(string $model, mixed $value): ?string
    {
        return filled($value) ? $model::query()->whereKey($value)->value('name') : null;
    }
}
