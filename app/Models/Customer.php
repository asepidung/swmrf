<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Customer extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'customer_group_id',
        'customer_segment_id',
        'address',
        'top',
        'default_discount',
        'pic',
        'phone',
        'required_documents',
        'invoice_exchange',
        'is_active',
    ];

    protected $casts = [
        'default_discount' => 'integer',
        'required_documents' => 'array',
        'invoice_exchange' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Master data yang menentukan uang (TOP, diskon, grup harga) -- wajib
     * memakai activity log seperti Warehouse/Grade.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Keputusan Owner, 7 September 2026: nama dan alamat pelanggan wajib
     * huruf besar, mengikuti pola yang sudah baku di CustomerGroup/
     * CustomerSegment/Product/dst. CSS `text-transform:uppercase` di form
     * hanya visual -- nilai yang benar-benar tersimpan tetap apa adanya
     * kalau tidak ditegaskan di sini.
     *
     * Nama juga sudah di-uppercase di `KeepsCustomerInAGroup` SEBELUM dipakai
     * mencocokkan/membuat CustomerGroup; mutator ini sengaja tetap ada supaya
     * jalur lain (import, tinker, seeder) tidak lolos tanpa uppercase.
     */
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtoupper(trim($value));
    }

    public function setAddressAttribute($value)
    {
        $this->attributes['address'] = $value === null ? null : strtoupper(trim($value));
    }

    public function setPicAttribute($value)
    {
        $this->attributes['pic'] = $value === null ? null : strtoupper(trim($value));
    }

    public function group()
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function segment()
    {
        return $this->belongsTo(CustomerSegment::class, 'customer_segment_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    public function receivables()
    {
        return $this->hasMany(Receivable::class, 'customer_id');
    }

    public function salesReturns()
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class, 'customer_id');
    }
}
