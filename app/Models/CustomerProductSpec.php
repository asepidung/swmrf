<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Spek tetap sebuah customer untuk sebuah produk (#579).
 *
 * Inilah SUMBER yang mengisi baris Sales Order secara otomatis. Baris SO
 * tidak merujuk ke sini: ia menyimpan salinannya sendiri di
 * `sales_order_items.spec`, jadi mengubah spek tetap tidak menyentuh SO yang
 * sudah dibuat. Semua bentuk salinan itu (array dengan kunci {@see FIELDS})
 * dibaca dan ditulis lewat {@see normalize()} dan {@see summarize()}.
 */
class CustomerProductSpec extends Model
{
    use LogsActivity;

    /** Kolom spek, urutannya juga urutan tampil di ringkasan. */
    public const FIELDS = ['weight_per_pack', 'cut', 'condition', 'packaging', 'note'];

    /** Nilai tersimpan => label. Istilah ini sama di Indonesia dan Inggris. */
    public const CONDITIONS = [
        'chill' => 'Chill',
        'frozen' => 'Frozen',
        'chill_aging' => 'Chill aging',
        'frozen_aging' => 'Frozen aging',
    ];

    protected $fillable = [
        'customer_id',
        'product_id',
        ...self::FIELDS,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['customer.name', 'product.name', ...self::FIELDS])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** Spek tetap ini dalam bentuk salinan untuk baris SO; null bila semuanya kosong. */
    public function snapshot(): ?array
    {
        return static::normalize($this->only(self::FIELDS));
    }

    /**
     * Rapikan spek jadi array berkunci {@see FIELDS} yang hanya berisi isian,
     * atau null bila tidak ada satu pun. Dipakai untuk apa pun yang akan
     * disimpan sebagai spek, supaya "kosong" selalu berarti null dan tidak
     * ada dua bentuk kosong yang berbeda (`[]`, kunci berisi string kosong).
     */
    public static function normalize(?array $spec): ?array
    {
        $clean = [];

        foreach (self::FIELDS as $field) {
            $value = trim((string) ($spec[$field] ?? ''));

            if ($value !== '') {
                $clean[$field] = $value;
            }
        }

        if (isset($clean['condition']) && ! isset(self::CONDITIONS[$clean['condition']])) {
            unset($clean['condition']);
        }

        return $clean === [] ? null : $clean;
    }

    /**
     * Bentuk teks salinan di form SO. Field form tidak bisa menampung array
     * dengan aman (inputnya akan mencoba menampilkannya sebagai teks), jadi
     * form menyimpannya sebagai JSON.
     */
    public static function encode(?array $spec): ?string
    {
        $spec = static::normalize($spec);

        return $spec === null ? null : json_encode($spec, JSON_UNESCAPED_UNICODE);
    }

    /** Kebalikan {@see encode()}; menerima juga array yang sudah jadi (dari kolom json). */
    public static function decode(array|string|null $value): ?array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? static::normalize($value) : null;
    }

    /**
     * Ringkasan satu baris, mis. "1 kg/pack · soup 4x4 cm · Chill · vacuum".
     *
     * @param  array|string|null  $spec  array, JSON dari form, atau null
     */
    public static function summarize(array|string|null $spec): string
    {
        $spec = static::decode($spec);

        if ($spec === null) {
            return '';
        }

        if (isset($spec['condition'])) {
            $spec['condition'] = self::CONDITIONS[$spec['condition']];
        }

        return implode(' · ', array_map(
            fn (string $value): string => preg_replace('/\s+/', ' ', $value),
            $spec,
        ));
    }

    /**
     * Spek tetap untuk satu customer dan beberapa produk sekaligus,
     * dalam bentuk salinan, dikunci dengan id produk.
     *
     * Satu kueri untuk seluruh baris SO, bukan satu kueri per baris.
     *
     * @param  array<int, int|string|null>  $productIds
     * @return array<int, array>
     */
    public static function snapshotsFor(int|string|null $customerId, array $productIds): array
    {
        $productIds = array_values(array_filter($productIds));

        if (! $customerId || $productIds === []) {
            return [];
        }

        return static::query()
            ->where('customer_id', $customerId)
            ->whereIn('product_id', $productIds)
            ->get()
            ->mapWithKeys(fn (self $spec): array => [$spec->product_id => $spec->snapshot()])
            ->filter()
            ->all();
    }

    /**
     * Simpan (atau hapus, bila kosong) spek tetap sebuah pasangan.
     *
     * Satu-satunya pintu tulis dari form Sales Order, dan izinnya diperiksa
     * DI SINI, bukan di tombolnya: tombol yang disembunyikan tidak menghentikan
     * permintaan Livewire yang dikirim langsung.
     */
    public static function saveFixed(int|string $customerId, int|string $productId, ?array $spec, ?User $user = null): ?self
    {
        Gate::forUser($user ?? auth()->user())->authorize('create', self::class);

        $spec = static::normalize($spec);
        $pair = ['customer_id' => $customerId, 'product_id' => $productId];

        if ($spec === null) {
            static::query()->where($pair)->get()->each->delete();

            return null;
        }

        return static::query()->updateOrCreate($pair, $spec + array_fill_keys(self::FIELDS, null));
    }

    public static function conditionLabel(?string $condition): ?string
    {
        return self::CONDITIONS[$condition] ?? null;
    }
}
