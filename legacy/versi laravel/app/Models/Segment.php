<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Segment extends Model
{
    protected $fillable = ['name'];
}
