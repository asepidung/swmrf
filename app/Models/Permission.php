<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['name', 'description'];

    /**
     * The users that belong to the permission.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_permissions');
    }
}
