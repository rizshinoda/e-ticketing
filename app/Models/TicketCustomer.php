<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'online_billing_id',
        'customer_name',
        'site_name',
        'no_jaringan',
        'reported_via',
    ];

    /**
     * Ticket induk.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(
            Ticket::class,
            'ticket_id'
        );
    }

    /**
     * Online Billing yang terkait.
     */
    public function onlineBilling(): BelongsTo
    {
        return $this->belongsTo(
            OnlineBilling::class,
            'online_billing_id'
        );
    }
}
