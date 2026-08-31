<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_incident_id',
        'rfo_number',
        'content',
        'created_by',
    ];

    /**
     * Incident yang menjadi sumber RFO.
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(
            TicketIncident::class,
            'ticket_incident_id'
        );
    }

    /**
     * User yang membuat RFO.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}
