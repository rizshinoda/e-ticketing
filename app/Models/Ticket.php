<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'ticket_type',
        'description',
        'priority',
        'status',
        'created_by',
        'resolved_by',
        'closed_by',
        'reported_at',
        'first_response_at',
        'resolved_at',
        'downtime_minutes',
        'closed_at',
    ];

    protected $casts = [
        'reported_at'       => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at'       => 'datetime',
        'closed_at'         => 'datetime',
    ];

    /**
     * User yang membuat dan menangani ticket.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    /**
     * User yang melakukan resolve.
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'resolved_by'
        );
    }

    /**
     * User yang melakukan close.
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'closed_by'
        );
    }

    /**
     * Customer/site yang terdampak.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(
            TicketCustomer::class,
            'ticket_id'
        );
    }

    /**
     * History progress/activity ticket.
     */
    public function updates(): HasMany
    {
        return $this->hasMany(
            TicketUpdate::class,
            'ticket_id'
        );
    }

    /**
     * Stop Clock pada ticket.
     */
    public function stopClocks(): HasMany
    {
        return $this->hasMany(
            TicketStopClock::class,
            'ticket_id'
        );
    }
    public function rfos(): HasMany
    {
        return $this->hasMany(
            Rfo::class,
            'ticket_id'
        );
    }
}
