<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'resolution',
    ];

    protected $casts = [
        'reported_at'       => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at'       => 'datetime',
        'closed_at'         => 'datetime',
    ];
    protected $appends = [
        'current_priority',
        'ticket_age',

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
    public function incidents(): HasMany
    {
        return $this->hasMany(TicketIncident::class, 'ticket_id');
    }
    public function latestIncident(): HasOne
    {
        return $this->hasOne(TicketIncident::class, 'ticket_id')
            ->latestOfMany('incident_number');
    }
    public function calculateCurrentPriority(): string
    {
        if ($this->status === 'closed') {
            return $this->priority;
        }

        $elapsedMinutes = (int) $this->reported_at->diffInMinutes(now());

        $escalationCount = (int) floor($elapsedMinutes / 240);

        $levels = [
            'low' => 0,
            'medium' => 1,
            'high' => 2,
            'critical' => 3,
        ];

        $priorities = [
            'low',
            'medium',
            'high',
            'critical',
        ];

        $currentLevel = $levels[$this->priority];

        $newLevel = min(
            $currentLevel + $escalationCount,
            3
        );

        return $priorities[$newLevel];
    }
    public function getCurrentPriorityAttribute(): string
    {
        return $this->calculateCurrentPriority();
    }

    public function getTicketAgeAttribute(): string
    {
        $endTime = $this->closed_at ?? now();

        $minutes = (int) $this->reported_at->diffInMinutes($endTime);

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return "{$hours} jam {$remainingMinutes} menit";
    }

    public function calculateDowntimeSeconds(): int
    {
        $incident = $this->latestIncident()
            ->with('category')
            ->first();

        if (!$incident) {
            return 0;
        }

        // Jika kendala bukan kategori downtime,
        // maka SLA timer tidak berjalan.
        if (!$incident->category?->is_downtime) {
            return 0;
        }

        $start = $incident->reported_at;
        $end = $incident->resolved_at ?? now();

        $totalSeconds = $start->diffInSeconds($end);

        $stopClockSeconds = $this->stopClocks()
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('started_at', [$start, $end])
                    ->orWhereBetween('ended_at', [$start, $end])
                    ->orWhere(function ($query) use ($start, $end) {
                        $query->where('started_at', '<=', $start)
                            ->where(function ($query) use ($end) {
                                $query->whereNull('ended_at')
                                    ->orWhere('ended_at', '>=', $end);
                            });
                    });
            })
            ->get()
            ->sum(function ($stopClock) use ($end) {
                $stopStart = $stopClock->started_at;
                $stopEnd = $stopClock->ended_at ?? $end;

                return $stopStart->diffInSeconds($stopEnd);
            });

        return max(0, $totalSeconds - $stopClockSeconds);
    }
}
