<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentStopClock extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_incident_id',
        'started_at',
        'ended_at',
        'reason',
        'started_by',
        'ended_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    /**
     * Incident yang mengalami Stop Clock.
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(
            TicketIncident::class,
            'ticket_incident_id'
        );
    }

    /**
     * User yang memulai Stop Clock.
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'started_by'
        );
    }

    /**
     * User yang mengakhiri Stop Clock.
     */
    public function ender(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'ended_by'
        );
    }
}
