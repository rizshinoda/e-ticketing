<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_downtime',
    ];

    protected $casts = [
        'is_downtime' => 'boolean',
    ];

    /**
     * Ticket incident yang menggunakan kategori/kendala ini.
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(
            TicketIncident::class,
            'kendala_id'
        );
    }
}
