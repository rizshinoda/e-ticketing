<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pelanggan extends Model
{
    protected $table = 'pelanggans';

    public function onlineBillings(): HasMany
    {
        return $this->hasMany(
            OnlineBilling::class,
            'pelanggan_id'
        );
    }
}
