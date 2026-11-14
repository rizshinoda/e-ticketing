<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketKpiContribution extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'credit',
    ];

    protected function casts(): array
    {
        return [
            'credit' => 'decimal:6',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
