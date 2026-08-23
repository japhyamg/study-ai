<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A weighted component of a term score — e.g. "CA1" worth 20%, "Exam" worth 60%.
 *
 * Schools define their own set; the weights should total 100 across all active
 * types, which {@see totalWeight()} lets callers verify.
 */
class AssessmentType extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'school_id', 'name', 'code', 'max_score', 'weight_percent', 'position', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'max_score' => 'integer',
            'weight_percent' => 'decimal:2',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Combined weight of all active types for a school — should be 100. */
    public static function totalWeight(string $schoolId): float
    {
        return (float) static::where('school_id', $schoolId)->active()->sum('weight_percent');
    }

    public static function weightsBalance(string $schoolId): bool
    {
        return abs(static::totalWeight($schoolId) - 100.0) < 0.01;
    }

    /** Convert a raw score on this component into its weighted contribution. */
    public function weightedScore(float $rawScore): float
    {
        if ($this->max_score <= 0) {
            return 0.0;
        }

        return round(($rawScore / $this->max_score) * (float) $this->weight_percent, 2);
    }
}
