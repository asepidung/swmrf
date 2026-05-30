<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticCategory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticCategory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticCategory whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LogisticCategory whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LogisticCategory extends Model
{
    protected $fillable = ['name'];
}
