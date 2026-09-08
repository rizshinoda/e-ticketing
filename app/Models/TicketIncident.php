<?php

namespace App\Models;

use App\Models\IncidentStopClock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketIncident extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_customer_id',
        'incident_number',
        'kendala_id',
        'reported_at',
        'first_response_at',
    ];

    protected $casts = [
        'reported_at'       => 'datetime',
        'first_response_at' => 'datetime',
    ];

    /**
     * Customer/site yang mengalami incident.
     */
    public function ticketCustomer(): BelongsTo
    {
        return $this->belongsTo(
            TicketCustomer::class,
            'ticket_customer_id'
        );
    }

    /**
     * Kendala incident.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(
            TicketCategory::class,
            'kendala_id'
        );
    }

    /**
     * RFO untuk incident.
     */
    public function rfos(): HasMany
    {
        return $this->hasMany(
            Rfo::class,
            'ticket_incident_id'
        );
    }
}
