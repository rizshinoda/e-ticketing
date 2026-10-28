<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaReport extends Model
{
    protected $fillable = [
        'report_key',
        'pelanggan_id',
        'customer_name',
        'scope',
        'online_billing_id',
        'site_name',
        'instansi_id',
        'instansi_name',
        'month',
        'year',
        'period_start',
        'period_end',
        'target_sla_percentage',
        'availability_percentage',
        'downtime_seconds',
        'total_service_seconds',
        'is_sla_met',
        'site_count',
        'snapshot',
        'finalized_by',
        'finalized_at',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'target_sla_percentage' => 'decimal:4',
        'availability_percentage' => 'decimal:4',
        'downtime_seconds' => 'integer',
        'total_service_seconds' => 'integer',
        'is_sla_met' => 'boolean',
        'site_count' => 'integer',
        'snapshot' => 'array',
        'finalized_at' => 'datetime',
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

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'finalized_by'
        );
    }
}
