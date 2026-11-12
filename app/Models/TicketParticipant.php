<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketParticipant extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'added_by',
        'joined_at',
        'left_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    // Tiket yang diikuti
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    // Staf yang menjadi participant
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // User yang menambahkan participant
    public function adder(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'added_by'
        );
    }
}
