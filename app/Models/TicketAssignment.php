<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAssignment extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'assigned_by',
        'assigned_at',
        'ended_at',
        'ended_by',
        'handover_note',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    // Tiket yang ditangani
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    // PIC yang ditugaskan
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // User yang melakukan penugasan
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_by'
        );
    }

    // User yang mengakhiri penugasan
    public function ender(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'ended_by'
        );
    }
}
