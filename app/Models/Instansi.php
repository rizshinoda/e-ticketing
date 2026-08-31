<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instansi extends Model
{
    protected $table = 'instansis';

    public function onlineBillings(): HasMany
    {
        return $this->hasMany(
            OnlineBilling::class,
            'instansi_id'
        );
    }
}
