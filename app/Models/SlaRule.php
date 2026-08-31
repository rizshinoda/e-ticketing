<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'pelanggan_id',
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

    /**
     * Pelanggan pemilik SLA.
     */
    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(
            Pelanggan::class,
            'pelanggan_id'
        );
    }

    /**
     * SLA khusus Online Billing/site.
     */
    public function onlineBilling(): BelongsTo
    {
        return $this->belongsTo(
            OnlineBilling::class,
            'online_billing_id'
        );
    }
}
