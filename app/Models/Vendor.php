<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    protected $table = 'vendors';

    public function onlineBillings(): HasMany
    {
        return $this->hasMany(
            OnlineBilling::class,
            'vendor_id'
        );
    }
}
