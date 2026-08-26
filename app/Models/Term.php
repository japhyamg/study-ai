<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A term within an {@see AcademicSession}. `sequence` gives an explicit order
 * (1st, 2nd, 3rd…) so terms sort correctly regardless of naming.
 */
class Term extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'school_id', 'academic_session_id', 'name', 'sequence',
        'is_current', 'start_date', 'end_date', 'resumption_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'resumption_date' => 'date',
            'is_current' => 'boolean',
            'sequence' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /** Make this the school's current term; unsets any other. */
    public function makeCurrent(): void
    {
        static::where('school_id', $this->school_id)
            ->where('id', '!=', $this->id)
            ->update(['is_current' => false]);

        $this->forceFill(['is_current' => true])->save();

        School::whereKey($this->school_id)->update(['current_term_id' => $this->id]);
    }

    /** "First Term · 2024/2025" */
    public function displayName(): string
    {
        return $this->name.($this->academicSession ? ' · '.$this->academicSession->name : '');
    }

    public function isActive(): bool
    {
        if (! $this->start_date || ! $this->end_date) {
            return $this->is_current;
        }

        return now()->between($this->start_date, $this->end_date);
    }
}
