<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiEvaluationDetail extends Model
{
    protected $fillable = [
        'kpi_evaluation_id',
        'indicator_code',
        'actual_value',
        'target_value',
        'weight',
        'score',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'actual_value' => 'decimal:2',
            'target_value' => 'decimal:2',
            'weight' => 'decimal:2',
            'score' => 'decimal:2',
        ];
    }

    // Evaluasi KPI induk
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(
            KpiEvaluation::class,
            'kpi_evaluation_id'
        );
    }
}
