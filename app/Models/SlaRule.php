<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaRule extends Model
{
    protected $fillable = [
        'pelanggan_id',
        'scope',
        'online_billing_id',
        'sla_percentage',
        'effective_from',
        'effective_until',
    ];

    protected $casts = [
        'sla_percentage' => 'decimal:4',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(
            Pelanggan::class,
            'pelanggan_id'
        );
    }

    public function onlineBilling(): BelongsTo
    {
        return $this->belongsTo(
            OnlineBilling::class,
            'online_billing_id'
        );
    }
}
