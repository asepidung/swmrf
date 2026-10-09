<?php

namespace App\Filament\Support;

use App\Models\CattleClass;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\Driver;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\ProductCategory;
use App\Models\Vehicle;
use Closure;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * Tombol "+" (membuat master baru dari dalam dropdown), diseragamkan (issue
 * #512, keputusan Owner 8 Oktober 2026).
 *
 * HANYA untuk master berisian pendek: satuan, kategori, supir, kendaraan, kelas
 * sapi, segmen dan grup customer. Produk, customer, supplier, dan material
 * TIDAK memakainya -- fieldnya banyak (keputusan Owner); alurnya cukup membuka
 * tab baru, lalu mengetik ulang di dropdown yang mencari ke server
 * (`MasterSelect`).
 *
 * Aturan yang sama di semua tempat:
 *  - bentuk formnya sama dengan form master aslinya;
 *  - tombol HANYA tampil bagi pemegang izin membuat master itu, dan karena
 *    aksi yang tersembunyi dianggap nonaktif oleh Filament, permintaan yang
 *    dikirim langsung tanpa izin pun ditolak server;
 *  - nama disimpan HURUF BESAR dan keunikannya tidak peka huruf besar/kecil
 *    (SQLite membedakan, MySQL tidak -- jadi dicek sendiri);
 *  - nilai yang baru dibuat langsung terpilih (perilaku bawaan Filament).
 *
 * Penjaganya: `QuickCreateGuardTest`.
 */
class QuickCreate
{
    /** @var array<string, class-string<\Illuminate\Database\Eloquent\Model>> */
    private const MODELS = [
        'materialUnit' => MaterialUnit::class,
        'materialCategory' => MaterialCategory::class,
        'productCategory' => ProductCategory::class,
        'driver' => Driver::class,
        'vehicle' => Vehicle::class,
        'cattleClass' => CattleClass::class,
        'customerSegment' => CustomerSegment::class,
        'customerGroup' => CustomerGroup::class,
    ];

    /** Model sebuah jenis master. */
    public static function model(string $kind): string
    {
        return self::MODELS[$kind] ?? throw new \InvalidArgumentException("Unknown master kind: {$kind}");
    }

    /**
     * Isian form "+" untuk sebuah master.
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function schema(string $kind): array
    {
        return match ($kind) {
            'materialUnit', 'materialCategory', 'cattleClass', 'customerSegment' => [
                self::uppercaseName($kind),
            ],

            'productCategory' => [
                TextInput::make('name')
                    ->label(fn () => __('Category Name'))
                    ->required()
                    ->maxLength(255)
                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                    ->dehydrateStateUsing(fn ($state) => strtoupper((string) $state))
                    ->rule(fn () => self::uniqueIgnoringCase($kind)),
                TextInput::make('prefix')
                    ->label(fn () => __('Prefix (Code)'))
                    ->required()
                    ->numeric()
                    ->unique('product_categories', 'prefix')
                    // Usulkan prefix berikutnya supaya operator tidak perlu membuka
                    // daftar kategori dulu untuk mencari yang terpakai.
                    ->default(fn () => ProductCategory::max('prefix') + 1)
                    ->helperText(fn () => __('Suggested from the highest existing prefix. Change it if needed.')),
            ],

            'driver' => [
                TextInput::make('name')
                    ->label(fn () => __('Name'))
                    ->required()
                    ->maxLength(255)
                    ->rule(fn () => self::uniqueIgnoringCase($kind)),
                Toggle::make('is_active')->default(true),
            ],

            'vehicle' => [
                TextInput::make('vehicle_type')->required()->maxLength(255),
                TextInput::make('police_number')
                    ->required()
                    ->maxLength(255)
                    ->rule(fn () => self::uniqueIgnoringCase($kind, 'police_number')),
                Toggle::make('is_active')->default(true),
            ],

            'customerGroup' => [
                self::uppercaseName($kind),
                TextInput::make('top')
                    ->label(fn () => __('TOP'))
                    ->suffix(__('days'))
                    ->required()
                    ->extraInputAttributes(['inputmode' => 'numeric', 'class' => 'text-right'])
                    ->rules(['integer', 'min:0']),
                TextInput::make('head_office_pic')
                    ->label(fn () => __('Head Office PIC'))
                    ->maxLength(255)
                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                Textarea::make('head_office_address')
                    ->label(fn () => __('Head Office Address'))
                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                    ->columnSpanFull(),
            ],

            default => throw new \InvalidArgumentException("Unknown master kind: {$kind}"),
        };
    }

    /**
     * Pengaturan tombol "+": hanya tampil bagi yang berhak membuat master itu.
     * Dipasang lewat `->createOptionAction(QuickCreate::action('driver'))`.
     */
    public static function action(string $kind): Closure
    {
        $model = self::model($kind);

        return fn (Action $action): Action => $action
            ->modalWidth('md')
            ->visible(fn (): bool => auth()->user()?->can('create', $model) ?? false);
    }

    /** Nama huruf besar, unik tanpa memandang huruf besar/kecil. */
    private static function uppercaseName(string $kind): TextInput
    {
        return TextInput::make('name')
            ->label(fn () => __('Name'))
            ->required()
            ->maxLength(255)
            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
            ->dehydrateStateUsing(fn ($state) => strtoupper((string) $state))
            ->rule(fn () => self::uniqueIgnoringCase($kind));
    }

    /** Aturan unik yang tidak peka huruf besar/kecil (SQLite peka, MySQL tidak). */
    private static function uniqueIgnoringCase(string $kind, string $column = 'name'): Closure
    {
        $model = self::model($kind);

        return function (string $attribute, mixed $value, Closure $fail) use ($model, $column): void {
            if (filled($value) && $model::query()->whereRaw("UPPER({$column}) = ?", [strtoupper((string) $value)])->exists()) {
                $fail(__('This name is already registered. Use another one.'));
            }
        };
    }
}
