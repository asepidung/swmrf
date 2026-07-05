<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MaterialUsageHeader extends Model
{
    protected $table = 'material_usage_headers';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'material_count' => 'integer',
        'total_qty' => 'float',
    ];

    public function usageable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'usageable_type', 'usageable_id');
    }

    public function usages()
    {
        return $this->hasMany(MaterialUsage::class, 'usageable_id', 'usageable_id')
            ->where('usageable_type', $this->usageable_type);
    }
}
