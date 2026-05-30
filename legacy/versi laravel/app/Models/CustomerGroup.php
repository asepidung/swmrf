<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerGroup whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerGroup whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerGroup whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CustomerGroup extends Model
{
    protected $fillable = ['name', 'description'];
}
