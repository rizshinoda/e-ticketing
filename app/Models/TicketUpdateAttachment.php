<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketUpdateAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_update_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
    ];

    /**
     * Update/progress yang memiliki attachment.
     */
    public function ticketUpdate(): BelongsTo
    {
        return $this->belongsTo(
            TicketUpdate::class,
            'ticket_update_id'
        );
    }
}
