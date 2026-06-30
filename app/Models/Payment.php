<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Payment extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'payment_number',
        'customer_group_id',
        'bank_account_id',
        'payment_date',
        'amount',
        'total_deduction',
        'reference_number',
        'note',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'total_deduction' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(PaymentDeduction::class, 'payment_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->payment_number)) {
                $currentYearFull = date('Y');
                $year2Digit = date('y');
                $currentMonth = date('n');
                
                $romanMonths = [
                    1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
                    5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
                    9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
                ];
                $romanMonth = $romanMonths[$currentMonth];

                $lastPayment = self::whereYear('created_at', $currentYearFull)
                                   ->orderBy('id', 'desc')
                                   ->first();

                if ($lastPayment && preg_match('/PAY-(\d+)\/[A-Z]+\/\d+/', $lastPayment->payment_number, $matches)) {
                    $lastNumber = intval($matches[1]);
                    $newNumber = $lastNumber + 1;
                } else {
                    $newNumber = 1;
                }

                $paddedNumber = str_pad($newNumber, 4, '0', STR_PAD_LEFT);
                $model->payment_number = "PAY-{$paddedNumber}/{$romanMonth}/{$year2Digit}";
            }

            if (empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }
}
