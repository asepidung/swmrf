<?php

namespace App\Models;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesReturn extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'return_number',
        'return_date',
        'delivery_order_id',
        'customer_id',
        'note',
        'status',
        'created_by',
    ];

    protected $casts = [
        'return_date' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->return_number)) {
                $model->return_number = DocumentNumber::next(
                    query: static::withTrashed(),
                    column: 'return_number',
                    prefix: 'SR#'.date('y'),
                    padding: 3,
                );
            }
            if (empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
        });
    }

    /**
     * Setujui retur ini: seluruh barangnya masuk ke stok.
     *
     * @throws \RuntimeException
     */
    public function approve(): void
    {
        if ($this->status !== 'Draft') {
            throw new \RuntimeException(__('Only a draft return can be approved.'));
        }

        if ($this->items->isEmpty()) {
            throw new \RuntimeException(__('This return has no item yet.'));
        }

        DB::transaction(function (): void {
            $this->update(['status' => 'Approved']);

            foreach ($this->items as $item) {
                BeefStock::create([
                    'barcode' => $item->barcode,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'grade_id' => $item->grade_id,
                    'weight' => $item->weight,
                    'qty_pcs' => $item->qty_pcs,
                    'ph_level' => $item->ph_level,
                    'pack_date' => $item->pack_date,
                    'exp_date' => $item->exp_date,
                    'origin' => $item->origin,
                    'status' => 'IN_STOCK',
                    'note' => 'Sales Return '.$this->return_number,
                ]);

                BeefStockMovement::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'condition' => $item->grade_id,
                    'barcode' => $item->barcode,
                    'transaction_type' => 'SALES_RETURN',
                    'reference_document' => $this->return_number,
                    'weight_in' => $item->weight,
                    'pcs_in' => $item->qty_pcs,
                    'created_by' => Auth::id(),
                    'note' => 'Sales Return from Customer',
                ]);
            }
        });
    }

    /**
     * Buka kunci retur ini: seluruh barangnya ditarik kembali dari stok.
     *
     * SATU RUMAH untuk keduanya. Rutin ini dulu disalin utuh di halaman Edit
     * DAN halaman View -- termasuk celah izinnya, sehingga menambal yang satu
     * meninggalkan yang lain tetap terbuka. Pola yang sama sudah pernah
     * ditemukan pada saldo hutang yang disalin enam kali dan rumus tagihan
     * yang disalin lima kali.
     *
     * Barangnya diperiksa lebih dulu SEMUANYA, baru ditarik. Menarik separuh
     * lalu berhenti di tengah karena satu barang sudah terlanjur dikirim lagi
     * meninggalkan stok yang tidak cocok dengan dokumen mana pun.
     *
     * @throws \RuntimeException
     */
    public function unlock(): void
    {
        if ($this->status !== 'Approved') {
            throw new \RuntimeException(__('Only an approved return can be unlocked.'));
        }

        DB::transaction(function (): void {
            foreach ($this->items as $item) {
                $stock = BeefStock::where('barcode', $item->barcode)->lockForUpdate()->first();

                if (! $stock) {
                    throw new \RuntimeException(__('Item :barcode is no longer in stock (already used or shipped).', [
                        'barcode' => $item->barcode,
                    ]));
                }

                if ($stock->status !== 'IN_STOCK') {
                    throw new \RuntimeException(__('Item :barcode is no longer in the warehouse (status: :status).', [
                        'barcode' => $item->barcode,
                        'status' => $stock->status,
                    ]));
                }
            }

            foreach ($this->items as $item) {
                BeefStockMovement::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'condition' => $item->grade_id,
                    'barcode' => $item->barcode,
                    'transaction_type' => 'CANCEL_SALES_RETURN',
                    'reference_document' => $this->return_number,
                    'weight_out' => $item->weight,
                    'pcs_out' => $item->qty_pcs,
                    'created_by' => Auth::id(),
                    'note' => 'Unlock/Cancel Sales Return',
                ]);

                BeefStock::where('barcode', $item->barcode)->delete();
            }

            $this->update(['status' => 'Draft']);
        });
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}
