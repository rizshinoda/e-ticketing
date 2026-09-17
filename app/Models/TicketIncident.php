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
        'ticket_id',
        'incident_number',
        'kendala_id',
        'reported_at',
        'resolved_at',

    ];

    protected $casts = [
        'reported_at'       => 'datetime',
        'resolved_at'       => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
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
