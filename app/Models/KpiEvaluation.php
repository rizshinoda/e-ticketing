<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiEvaluation extends Model
{
    protected $fillable = [
        'user_id',
        'period_start',
        'period_end',
        'total_incoming_tickets',
        'active_staff_count',
        'productivity_target',
        'productivity_score',
        'speed_score',
        'quality_score',
        'final_score',
        'status',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'finalized_at' => 'datetime',
            'productivity_target' => 'decimal:2',
            'productivity_score' => 'decimal:2',
            'speed_score' => 'decimal:2',
            'quality_score' => 'decimal:2',
            'final_score' => 'decimal:2',
        ];
    }

    // Staf yang dievaluasi
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Detail penilaian KPI
    public function details(): HasMany
    {
        return $this->hasMany(
            KpiEvaluationDetail::class
        );
    }
}
